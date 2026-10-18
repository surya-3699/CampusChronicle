<?php

require_once "app/Models/Certificate.php";
require_once "app/Models/Registration.php";
require_once "app/Controllers/AuthController.php";

class CertificateController
{
    private $certificate;
    private $registration;

    public function __construct()
    {
        $this->certificate = new Certificate();
        $this->registration = new Registration();
    }

    /* ==================================================
        DOWNLOAD (renders a printable certificate page —
        the person can use the browser's "Print > Save as
        PDF" to get a PDF copy, no server-side PDF library
        required)
    ================================================== */

    public function download()
    {
        AuthController::checkLogin();

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $certificate = $this->certificate->getCertificateById($id);

        if (!$certificate) {
            $_SESSION['error'] = 'Certificate not found.';
            header("Location: index.php?page=certificates");
            exit;
        }

        $isOwner = $certificate['user_id'] == ($_SESSION['user_id'] ?? null);
        $isAdmin = AuthController::isAdmin();

        if (!$isOwner && !$isAdmin) {
            $_SESSION['error'] = "You don't have permission to view that certificate.";
            header("Location: index.php?page=certificates");
            exit;
        }

        include "app/Views/user/certificate-view.php";
        exit;
    }

    /* ==================================================
        VERIFY (public lookup by certificate code)
    ================================================== */

    public function verify()
    {
        $code = trim($_GET['code'] ?? '');
        $result = null;

        if ($code !== '') {
            $result = $this->certificate->verifyCertificate($code);
        }

        include "app/Views/layouts/header.php";
        include "app/Views/user/verify-certificate.php";
        include "app/Views/layouts/footer.php";
    }

    /* ==================================================
        GENERATE (admin: issue a certificate for an
        approved / attended registration)
    ================================================== */

    public function generate()
    {
        AuthController::checkAdmin();

        $registrationId = (int)($_POST['registration_id'] ?? 0);
        $registration = $this->registration->getRegistrationById($registrationId);

        if (!$registration) {
            $_SESSION['error'] = 'Registration not found.';
            header("Location: index.php?page=admin-events");
            exit;
        }

        $result = $this->certificate->generateCertificate(
            $registration['user_id'],
            $registration['event_id'],
            $registration['id']
        );

        $_SESSION[$result['success'] ? 'success' : 'error'] = $result['message'];

        header('Location: index.php?page=admin-registrations&event_id='.(int)$registration['event_id']);
        exit;
    }

    public function award()
    {
        AuthController::checkAdmin();
        $registration=$this->registration->getRegistrationById((int)($_POST['registration_id'] ?? 0));
        if (!$registration) { http_response_code(404); exit('Registration not found.'); }
        $rank=filter_var($_POST['prize_rank'] ?? null,FILTER_VALIDATE_INT);
        $result=$this->certificate->generateCertificate($registration['user_id'],$registration['event_id'],$registration['id'],$rank===false?0:$rank);
        $_SESSION[$result['success']?'success':'error']=$result['message'];
        header('Location: index.php?page=admin-registrations&event_id='.(int)$registration['event_id']);exit;
    }

    public function settings()
    {
        AuthController::checkAdmin();
        require_once 'app/Models/CertificateSettings.php';
        $eventId=(int)($_POST['event_id'] ?? 0);
        try { (new CertificateSettings())->save($eventId,$_POST); $_SESSION['success']='Certificate settings saved. Previously issued certificates keep their original design.'; }
        catch (InvalidArgumentException $e) { $_SESSION['error']=$e->getMessage(); }
        header('Location: index.php?page=admin-registrations&event_id='.$eventId);exit;
    }

    public function backfill()
    {
        AuthController::checkAdmin();
        $eventId=(int)($_POST['event_id'] ?? 0);
        $result=$this->certificate->bulkGenerateCertificates($eventId);
        $_SESSION[$result['success']?'success':'error']=$result['message'];
        header('Location: index.php?page=admin-registrations&event_id='.$eventId);exit;
    }

    public function preview()
    {
        AuthController::checkAdmin();
        require_once 'app/Models/Event.php';
        require_once 'app/Models/CertificateSettings.php';
        $eventId=(int)($_GET['event_id'] ?? 0);
        $q=Database::connect()->prepare('SELECT * FROM events WHERE id=?');$q->execute([$eventId]);$event=$q->fetch();
        if (!$event) { http_response_code(404); exit('Event not found.'); }
        $type=($_GET['type'] ?? '')==='Winner'?'Winner':'Participation';
        $certificate=['id'=>0,'event_id'=>$eventId,'fullname'=>'Student Name','event_title'=>$event['title'],'event_date'=>$event['event_date'],'venue'=>$event['venue'],'faculty_name'=>$event['faculty_name'],'generated_at'=>date('Y-m-d H:i:s'),'certificate_code'=>'PREVIEW — NOT AN ISSUED CERTIFICATE','certificate_type'=>$type,'prize_rank'=>$type==='Winner'?1:null,'presentation_json'=>json_encode((new CertificateSettings())->presentation($eventId,$type))];
        $isPreview=true;
        include 'app/Views/user/certificate-view.php';exit;
    }
}
