<?php

require_once "config/database.php";
require_once "app/Services/Totp.php";
require_once "app/Controllers/AuthController.php";

/**
 * Administrator two-factor authentication (TOTP).
 *   two-factor          sign-in challenge (shown to an admin who has 2FA on but has not verified this session)
 *   admin-security      status + enrolment page
 *   two-factor-enable   POST: confirm the first code and switch 2FA on
 *   two-factor-disable  POST: switch 2FA off (needs a current code)
 * Enforcement itself lives in AuthController::checkAdmin().
 */
class TwoFactorController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    private function row(): array
    {
        $q = $this->db->prepare('SELECT email, totp_secret, totp_enabled, totp_last_step, totp_recovery FROM users WHERE id = ?');
        $q->execute([(int)($_SESSION['user_id'] ?? 0)]);
        return $q->fetch() ?: [];
    }

    private function userKey(): string
    {
        return '2fa|' . (int)($_SESSION['user_id'] ?? 0);
    }

    private function blocked(): bool
    {
        // 8 wrong codes per 15 minutes per account, from anywhere.
        return cc_throttle_count($this->userKey(), 900, false) >= 8;
    }

    private function fail(string $message): void
    {
        cc_throttle_hit($this->userKey(), 900, false);
        $_SESSION['error'] = $message;
    }

    private function lastStep(array $row): ?int
    {
        return isset($row['totp_last_step']) && $row['totp_last_step'] !== null ? (int)$row['totp_last_step'] : null;
    }

    private function redirect(string $page): void
    {
        header('Location: index.php?page=' . $page);
        exit;
    }

    /* ------------------------------------------------------------------ */
    /* Sign-in challenge                                                   */
    /* ------------------------------------------------------------------ */

    public function challenge()
    {
        AuthController::checkLogin();
        if (!AuthController::isAdmin()) {
            $this->redirect('home');
        }
        $row = $this->row();
        if ((int)($row['totp_enabled'] ?? 0) !== 1 || !empty($_SESSION['mfa_ok'])) {
            $this->redirect('admin');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $this->verifyChallenge($row);
        }

        include "app/Views/layouts/header.php";
        include "app/Views/auth/two_factor.php";
        include "app/Views/layouts/footer.php";
    }

    private function verifyChallenge(array $row): void
    {
        if ($this->blocked()) {
            $_SESSION['error'] = 'Too many wrong codes. Please wait 15 minutes and try again.';
            return;
        }

        $input = trim((string)($_POST['code'] ?? ''));
        $digits = preg_replace('/\s+/', '', $input);

        if (preg_match('/^\d{6}$/D', $digits)) {
            $secret = Totp::decrypt((string)($row['totp_secret'] ?? ''));
            if ($secret === null) {
                $_SESSION['error'] = 'Two-step verification cannot be checked right now. Ask the person who manages the server to run tools/reset-2fa.php for your account.';
                return;
            }
            $step = Totp::verify($secret, $digits, $this->lastStep($row));
            if ($step !== null) {
                // Conditional update = atomic replay guard: only a strictly newer step wins.
                $q = $this->db->prepare('UPDATE users SET totp_last_step = ? WHERE id = ? AND (totp_last_step IS NULL OR totp_last_step < ?)');
                $q->execute([$step, (int)$_SESSION['user_id'], $step]);
                if ($q->rowCount() === 1) {
                    $this->passed();
                }
            }
        } elseif (preg_match('/^[0-9a-fA-F]{4}-?[0-9a-fA-F]{4}$/D', $input)) {
            $hashes = json_decode((string)($row['totp_recovery'] ?? ''), true);
            $hashes = is_array($hashes) ? $hashes : [];
            $index = array_search(Totp::hashRecoveryCode($input), $hashes, true);
            if ($index !== false) {
                unset($hashes[$index]);
                $remaining = array_values($hashes);
                $q = $this->db->prepare('UPDATE users SET totp_recovery = ? WHERE id = ? AND totp_recovery = ?');
                $q->execute([json_encode($remaining), (int)$_SESSION['user_id'], $row['totp_recovery']]);
                if ($q->rowCount() === 1) {
                    $_SESSION['success'] = 'Recovery code accepted. You have ' . count($remaining) . ' left. Consider setting up your authenticator again.';
                    $this->passed();
                }
            }
        }

        $this->fail('That code is not valid. Check the current code in your authenticator app, or use an unused recovery code.');
    }

    private function passed(): void
    {
        session_regenerate_id(true);
        $_SESSION['mfa_ok'] = true;
        cc_throttle_clear($this->userKey(), false);
        $this->redirect('admin');
    }

    /* ------------------------------------------------------------------ */
    /* Settings / enrolment                                                */
    /* ------------------------------------------------------------------ */

    /** Data for views/admin/security.php */
    public function settings(): array
    {
        $row = $this->row();
        $enabled = (int)($row['totp_enabled'] ?? 0) === 1;
        $data = [
            'enabled'    => $enabled,
            'configured' => Totp::isConfigured(),
            'required'   => cc_env('CC_ADMIN_2FA_REQUIRED', '0') === '1',
            'secret'     => '',
            'uri'        => '',
            'recovery'   => [],
            'remaining'  => 0,
        ];
        if ($enabled) {
            $hashes = json_decode((string)($row['totp_recovery'] ?? ''), true);
            $data['remaining'] = is_array($hashes) ? count($hashes) : 0;
        } elseif ($data['configured']) {
            if (empty($_SESSION['totp_pending_secret'])) {
                $_SESSION['totp_pending_secret'] = Totp::newSecret();
            }
            $data['secret'] = $_SESSION['totp_pending_secret'];
            $data['uri'] = Totp::otpauthUri((string)($row['email'] ?? 'admin'), $data['secret']);
        }
        if (!empty($_SESSION['totp_new_recovery'])) {
            $data['recovery'] = $_SESSION['totp_new_recovery'];
            unset($_SESSION['totp_new_recovery']); // shown exactly once
        }
        return $data;
    }

    public function enable()
    {
        AuthController::checkAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->redirect('admin-security');
        }
        if (!Totp::isConfigured()) {
            $_SESSION['error'] = 'CC_APP_KEY is not set on the server, so authenticator secrets cannot be stored safely yet.';
            $this->redirect('admin-security');
        }
        $row = $this->row();
        if ((int)($row['totp_enabled'] ?? 0) === 1) {
            $this->redirect('admin-security');
        }
        $secret = (string)($_SESSION['totp_pending_secret'] ?? '');
        if ($secret === '') {
            $this->redirect('admin-security');
        }
        if ($this->blocked()) {
            $_SESSION['error'] = 'Too many wrong codes. Please wait 15 minutes and try again.';
            $this->redirect('admin-security');
        }

        $step = Totp::verify($secret, (string)($_POST['code'] ?? ''), null);
        if ($step === null) {
            $this->fail('That code did not match. Make sure the key was entered correctly and your phone\'s clock is set to automatic, then try the newest code.');
            $this->redirect('admin-security');
        }

        $codes = Totp::newRecoveryCodes();
        $hashes = array_map([Totp::class, 'hashRecoveryCode'], $codes);
        $q = $this->db->prepare('UPDATE users SET totp_secret = ?, totp_enabled = 1, totp_last_step = ?, totp_recovery = ? WHERE id = ?');
        $q->execute([Totp::encrypt($secret), $step, json_encode($hashes), (int)$_SESSION['user_id']]);

        unset($_SESSION['totp_pending_secret']);
        $_SESSION['mfa_ok'] = true;
        $_SESSION['totp_new_recovery'] = $codes;
        $_SESSION['success'] = 'Two-step verification is now ON for your account.';
        cc_throttle_clear($this->userKey(), false);
        $this->redirect('admin-security');
    }

    public function disable()
    {
        AuthController::checkAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->redirect('admin-security');
        }
        if (cc_env('CC_ADMIN_2FA_REQUIRED', '0') === '1') {
            $_SESSION['error'] = 'Two-step verification is required for all administrators on this site and cannot be turned off here.';
            $this->redirect('admin-security');
        }
        if ($this->blocked()) {
            $_SESSION['error'] = 'Too many wrong codes. Please wait 15 minutes and try again.';
            $this->redirect('admin-security');
        }

        $row = $this->row();
        $secret = Totp::decrypt((string)($row['totp_secret'] ?? ''));
        $step = $secret === null ? null : Totp::verify($secret, (string)($_POST['code'] ?? ''), $this->lastStep($row));
        if ($step === null) {
            $this->fail('Enter a current code from your authenticator app to turn two-step verification off.');
            $this->redirect('admin-security');
        }

        $q = $this->db->prepare('UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_last_step = NULL, totp_recovery = NULL WHERE id = ?');
        $q->execute([(int)$_SESSION['user_id']]);
        unset($_SESSION['mfa_ok']);
        cc_throttle_clear($this->userKey(), false);
        $_SESSION['success'] = 'Two-step verification has been turned off.';
        $this->redirect('admin-security');
    }
}
