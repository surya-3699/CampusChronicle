<?php

require_once "app/Models/User.php";

class AuthController
{

    private $user;

    public function __construct()
    {
        $this->user = new User();
    }

    /*==================================================
                    REGISTER
    ==================================================*/

    public function register()
    {

        if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['register'])) {
            return;
        }

        $fullname = trim($_POST['fullname']);
        $fullname = preg_replace('/\s+/', ' ', $fullname);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        /*=========================
            Empty Validation
        =========================*/

        if (
            empty($fullname) ||
            empty($email) ||
            empty($password) ||
            empty($confirmPassword)
        ) {

            $_SESSION['error']="Please fill all fields.";
            return;

        }

        /*=========================
            Email Validation
        =========================*/

        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){

            $_SESSION['error']="Please enter a valid email address.";
            return;

        }

        /*=========================
            Password Match
        =========================*/

        if($password!=$confirmPassword){

            $_SESSION['error']="Passwords do not match.";
            return;

        }

        /*=========================
            Strong Password
        =========================*/

        $pattern='/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/';

        if(strlen($password)>72 || !preg_match($pattern,$password)){

            $_SESSION['error']="Password must contain at least 8 characters, one uppercase letter, one lowercase letter, one number and one special character.";

            return;

        }

        /*=========================
            Encrypt Password
        =========================*/

        $password=password_hash($password,PASSWORD_DEFAULT);

 /*=========================
    Save User
=========================*/

if($this->user->register($fullname, $email, $password)){

    $user = $this->user->login($email);

    // Public registrations remain students; provision administrators with the CLI.
    

    $this->startSessionForUser($user);

    $_SESSION['success'] = "Welcome to Campus Chronicle!";

    header("Location:index.php?page=home");
    exit();

}else{

    $_SESSION['error'] = "Registration failed.";
    return;

}
}

    /*==================================================
                    LOGIN
    ==================================================*/

    public function login()
    {

        if($_SERVER["REQUEST_METHOD"]!="POST" || !isset($_POST['login'])){
            return;
        }

        $email=trim($_POST['email']);

        $password=$_POST['password'];

        // Failed-attempt throttle. Main rule: 8 failures per 15 min for this account FROM THIS
        // address, so an attacker cannot lock someone out of their own network. High ceiling: 60
        // failures per 15 min for the account from anywhere (only a distributed attack reaches it).
        $accountBucket='login|'.strtolower($email);
        if(cc_throttle_count($accountBucket,900,true)>=8 || cc_throttle_count($accountBucket,900,false)>=60){

            $_SESSION['error']='Too many failed sign-in attempts. Please wait 15 minutes, or use "Sign in with Google".';
            return;

        }

        $user=$this->user->login($email);

        if(!$user){

            cc_throttle_hit($accountBucket,900,true);
            cc_throttle_hit($accountBucket,900,false);

            // Do the same amount of hashing work as a real attempt so response time
            // does not reveal whether an account exists for this address.
            static $decoy = null;
            $decoy = $decoy ?? password_hash('decoy-password-for-timing', PASSWORD_DEFAULT);
            password_verify((string)$password, $decoy);

            $_SESSION['error']="Invalid email or password.";
            return;

        }

        if(!password_verify($password,$user['password'])){

            cc_throttle_hit($accountBucket,900,true);
            cc_throttle_hit($accountBucket,900,false);

            $_SESSION['error']="Invalid email or password.";
            return;

        }

        cc_throttle_clear($accountBucket,true);

        $this->startSessionForUser($user);

        header("Location:index.php?page=home");
        exit();

    }
    /*==================================================
                    GOOGLE LOGIN
    ==================================================*/

    public function googleLogin()
    {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            header("Location:index.php?page=login");
            exit();
        }

        require_once "config/google.php";
        require_once "app/Services/GoogleIdentityService.php";

        if (!GoogleAuthConfig::isConfigured()) {
            $_SESSION['error'] = "Google sign-in is not configured yet.";
            header("Location:index.php?page=login");
            exit();
        }

        $expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
        $receivedState = (string) ($_POST['state'] ?? '');
        $startedAt=(int)($_SESSION['google_oauth_started_at'] ?? 0);
        unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_started_at']);

        if (
            $expectedState === ''
            || time()-$startedAt>600
            || $receivedState === ''
            || !hash_equals($expectedState, $receivedState)
        ) {
            $_SESSION['error'] = "Google sign-in request could not be verified. Please try again.";
            header("Location:index.php?page=login");
            exit();
        }

        $credential = trim((string) ($_POST['credential'] ?? ''));
        if ($credential === '') {
            $_SESSION['error'] = "Google did not return a sign-in credential.";
            header("Location:index.php?page=login");
            exit();
        }

        try {
            $payload = GoogleIdentityService::verifyIdToken(
                $credential,
                GoogleAuthConfig::clientId(),
                $expectedState
            );

            $googleSub = trim((string) $payload['sub']);
            $email = strtolower(trim((string) $payload['email']));
            $fullname = trim((string) ($payload['name'] ?? ''));
            $profileImage = trim((string) ($payload['picture'] ?? ''));

            if ($fullname === '') {
                $fullname = strstr($email, '@', true) ?: 'Campus Chronicle Student';
            }

            $user = $this->user->getUserByGoogleSub($googleSub);

            if (!$user) {
                $emailUser = $this->user->login($email);

                if ($emailUser) {
                    $existingGoogleSub = trim((string) ($emailUser['google_sub'] ?? ''));
                    if ($existingGoogleSub !== '' && !hash_equals($existingGoogleSub, $googleSub)) {
                        throw new RuntimeException('This email is already linked to another Google account.');
                    }

                    if ($existingGoogleSub === '') {
                        if (!GoogleIdentityService::isGoogleAuthoritativeForEmail($payload)) {
                            throw new RuntimeException(
                                'This email already has a password account. Sign in with your password before linking Google.'
                            );
                        }

                        if (!$this->user->linkGoogleAccount($emailUser['id'], $googleSub)) {
                            throw new RuntimeException('Unable to link Google to your Campus Chronicle account.');
                        }
                    }
                    $user = $this->user->getUserById($emailUser['id']);
                    if (!$user || !hash_equals((string) ($user['google_sub'] ?? ''), $googleSub)) {
                        throw new RuntimeException('Unable to link Google to your Campus Chronicle account.');
                    }
                } else {
                    if (!$this->user->createGoogleUser($googleSub, $fullname, $email, $profileImage)) {
                        throw new RuntimeException('Unable to create your Campus Chronicle account.');
                    }

                    $user = $this->user->getUserByGoogleSub($googleSub);

                }
            }

            if (!$user) {
                throw new RuntimeException('Unable to load your Campus Chronicle account.');
            }

            $this->startSessionForUser($user);
            $_SESSION['success'] = "Signed in securely with Google.";

            header("Location:index.php?page=home");
            exit();
        } catch (Throwable $e) {
            error_log('Google sign-in failed: ' . $e->getMessage());

            $safeMessages = [
                'This email is already linked to another Google account.',
                'This email already has a password account. Sign in with your password before linking Google.',
            ];

            $_SESSION['error'] = in_array($e->getMessage(), $safeMessages, true)
                ? $e->getMessage()
                : "Google sign-in could not be completed. Please try again.";

            header("Location:index.php?page=login");
            exit();
        }
    }

    /*==================================================
                    LINKEDIN LOGIN
    ==================================================*/

    public function linkedinLogin()
    {
        require_once "config/linkedin.php";
        require_once "app/Services/LinkedInIdentityService.php";

        if (!LinkedInAuthConfig::isConfigured()) {
            $_SESSION['error'] = "LinkedIn sign-in is currently unavailable.";
            header("Location:index.php?page=login");
            exit();
        }

        try {
            $state = bin2hex(random_bytes(32));
            $_SESSION['linkedin_oauth_state'] = $state;
            $_SESSION['linkedin_oauth_started_at'] = time();

            $url = LinkedInIdentityService::authorizationUrl(
                LinkedInAuthConfig::clientId(),
                LinkedInAuthConfig::redirectUri(),
                $state
            );

            header('Location: ' . $url);
            exit();
        } catch (Throwable $e) {
            error_log('Unable to start LinkedIn sign-in: ' . $e->getMessage());
            $_SESSION['error'] = "LinkedIn sign-in could not be started. Please try again.";
            header("Location:index.php?page=login");
            exit();
        }
    }

    public function linkedinCallback()
    {
        require_once "config/linkedin.php";
        require_once "app/Services/LinkedInIdentityService.php";

        if (!LinkedInAuthConfig::isConfigured()) {
            $_SESSION['error'] = "LinkedIn sign-in is currently unavailable.";
            header("Location:index.php?page=login");
            exit();
        }

        $expectedState = (string) ($_SESSION['linkedin_oauth_state'] ?? '');
        $receivedState = (string) ($_GET['state'] ?? '');
        $startedAt = (int) ($_SESSION['linkedin_oauth_started_at'] ?? 0);
        unset($_SESSION['linkedin_oauth_state'], $_SESSION['linkedin_oauth_started_at']);

        if (
            $expectedState === ''
            || time()-$startedAt>600
            || $receivedState === ''
            || !hash_equals($expectedState, $receivedState)
            || $startedAt <= 0
            || (time() - $startedAt) > 600
        ) {
            $_SESSION['error'] = "LinkedIn sign-in request expired or could not be verified. Please try again.";
            header("Location:index.php?page=login");
            exit();
        }

        if (isset($_GET['error'])) {
            $_SESSION['error'] = "LinkedIn sign-in was cancelled or not approved.";
            header("Location:index.php?page=login");
            exit();
        }

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            $_SESSION['error'] = "LinkedIn did not return a sign-in code.";
            header("Location:index.php?page=login");
            exit();
        }

        try {
            $profile = LinkedInIdentityService::exchangeCodeForProfile(
                $code,
                LinkedInAuthConfig::clientId(),
                LinkedInAuthConfig::clientSecret(),
                LinkedInAuthConfig::redirectUri()
            );

            $linkedinSub = trim((string) $profile['sub']);
            $email = strtolower(trim((string) $profile['email']));
            $fullname = trim((string) ($profile['name'] ?? ''));
            $profileImage = trim((string) ($profile['picture'] ?? ''));

            if ($fullname === '') {
                $fullname = strstr($email, '@', true) ?: 'Campus Chronicle Student';
            }

            $user = $this->user->getUserByLinkedInSub($linkedinSub);

            if (!$user) {
                $emailUser = $this->user->login($email);

                // Do not silently merge accounts just because two identity
                // providers returned the same email address. Existing accounts
                // must be linked explicitly from an authenticated account flow.
                if ($emailUser) {
                    $existingLinkedInSub = trim((string) ($emailUser['linkedin_sub'] ?? ''));
                    if ($existingLinkedInSub !== '') {
                        throw new RuntimeException('This email is already linked to another LinkedIn account.');
                    }

                    throw new RuntimeException(
                        'An account with this email already exists. Sign in with your existing method first.'
                    );
                }

                if (!$this->user->createLinkedInUser($linkedinSub, $fullname, $email, $profileImage)) {
                    throw new RuntimeException('Unable to create your Campus Chronicle account.');
                }

                $user = $this->user->getUserByLinkedInSub($linkedinSub);

                
            }

            if (!$user) {
                throw new RuntimeException('Unable to load your Campus Chronicle account.');
            }

            $this->startSessionForUser($user);
            $_SESSION['success'] = "Signed in securely with LinkedIn.";

            header("Location:index.php?page=home");
            exit();
        } catch (Throwable $e) {
            error_log('LinkedIn sign-in failed: ' . $e->getMessage());

            $safeMessages = [
                'This email is already linked to another LinkedIn account.',
                'An account with this email already exists. Sign in with your existing method first.',
            ];

            $_SESSION['error'] = in_array($e->getMessage(), $safeMessages, true)
                ? $e->getMessage()
                : "LinkedIn sign-in could not be completed. Please try again.";

            header("Location:index.php?page=login");
            exit();
        }
    }

    public function forgotPassword()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
        $token = $_POST['reset_token'] ?? '';
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)) {
            $_SESSION['error']='Use a recovery link issued by campus support after identity verification.';
            return;
        }
        if (!is_string($password) || strlen($password)<12 || strlen($password)>72 || $password !== ($_POST['confirm_password'] ?? '')) {
            $_SESSION['error']='Use 12–72 characters and matching passwords.'; return;
        }
        $db=Database::connect();
        try {
            $db->beginTransaction();
            $q=$db->prepare('SELECT t.user_id FROM password_reset_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=? AND t.expires_at>NOW() AND u.email=? FOR UPDATE');
            $q->execute([hash('sha256',$token),$email]); $id=$q->fetchColumn();
            if (!$id) { $db->rollBack(); $_SESSION['error']='Recovery link is invalid or expired.'; return; }
            $q=$db->prepare('UPDATE users SET password=? WHERE id=?');
            $q->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
            $q=$db->prepare('DELETE FROM password_reset_tokens WHERE user_id=?'); $q->execute([$id]);
            $db->commit();
            $_SESSION['success']='Password updated. Please sign in.';
            header('Location: index.php?page=login'); exit;
        } catch (Throwable $e) {
            if($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
    private function startSessionForUser(array $user)
    {
        session_regenerate_id(true);
        unset($_SESSION['mfa_ok'], $_SESSION['totp_pending_secret'], $_SESSION['totp_new_recovery']);
        $_SESSION['authenticated_at']=time();
        $_SESSION['last_active']=time();
        $_SESSION['credential_version']=hash('sha256',$user['password']);
        $_SESSION['csrf_token']=bin2hex(random_bytes(32));

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['profile_image'] = $user['profile_image'] ?? 'default.png';
        $_SESSION['role'] = $user['role'] ?? 'student';
    }

    /*==================================================
                CHECK LOGIN
    ==================================================*/

    public static function checkLogin()
    {

        if(!isset($_SESSION['user_id'])){

            $_SESSION['error'] = "Please log in to continue.";

            header("Location:index.php?page=login");

            exit();

        }

    }

    /*==================================================
                CHECK ADMIN
    ==================================================*/

    public static function checkAdmin()
    {

        self::checkLogin();

        if(($_SESSION['role'] ?? 'student') !== 'admin'){

            $_SESSION['error'] = "You don't have permission to access the admin area.";

            header("Location:index.php?page=home");

            exit();

        }

        self::enforceSecondFactor();

    }

    /*==================================================
        TWO-STEP VERIFICATION for administrators.
        Runs for every admin page and action, whichever way the admin signed in
        (password, Google or LinkedIn).
        CC_ADMIN_2FA=off           emergency switch: disables the check entirely
        CC_ADMIN_2FA_REQUIRED=1    admins who have not enrolled are sent to the setup page
    ==================================================*/

    private static function enforceSecondFactor()
    {
        if (cc_env('CC_ADMIN_2FA', 'on') === 'off' || !empty($_SESSION['mfa_ok'])) {
            return;
        }

        try {
            $q = Database::connect()->prepare('SELECT totp_enabled FROM users WHERE id=?');
            $q->execute([$_SESSION['user_id']]);
            $enabled = ((int)$q->fetchColumn()) === 1;
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1054) {
                return; // column not installed yet (migration pending): the feature is not active
            }
            throw $e;    // any other database problem fails closed
        }

        if ($enabled) {
            header("Location:index.php?page=two-factor");
            exit();
        }

        $page = $_GET['page'] ?? '';
        if (cc_env('CC_ADMIN_2FA_REQUIRED', '0') === '1' && !in_array($page, ['admin-security', 'two-factor-enable'], true)) {
            $_SESSION['error'] = "Set up two-step verification to continue to the admin area.";
            header("Location:index.php?page=admin-security");
            exit();
        }
    }

    /*==================================================
                IS ADMIN (non-redirecting helper for views)
    ==================================================*/

    public static function isAdmin()
    {
        return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? 'student') === 'admin';
    }

    /*==================================================
                    LOGOUT
    ==================================================*/

    public function logout()
    {

        cc_destroy_session();

        header("Location:index.php?page=home");

        exit();

    }

}
