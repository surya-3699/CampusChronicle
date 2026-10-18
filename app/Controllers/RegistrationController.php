<?php
require_once "app/Models/Registration.php";
require_once "app/Controllers/AuthController.php";

class RegistrationController
{
    private $registration;

    public function __construct()
    {
        $this->registration = new Registration();
    }

    /* ==================================================
        APPROVE A REGISTRATION
    ================================================== */

    public function approve()
    {
        AuthController::checkAdmin();

        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $registration = $this->registration->getRegistrationById($id);

        if (!$id || !$registration) {
            $_SESSION['error'] = "Registration not found.";
            header("Location: index.php?page=admin-events");
            exit;
        }

        if ($this->registration->approveRegistration($id)) {
            $_SESSION['success'] = "Registration approved.";
        } else {
            $_SESSION['error'] = "Could not approve this registration.";
        }

        header("Location: index.php?page=admin-registrations&event_id=" . $registration['event_id']);
        exit;
    }

    /* ==================================================
        REJECT A REGISTRATION
    ================================================== */

    public function reject()
    {
        AuthController::checkAdmin();

        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? $_GET['reason'] ?? '');
        $registration = $this->registration->getRegistrationById($id);

        if (!$id || !$registration) {
            $_SESSION['error'] = "Registration not found.";
            header("Location: index.php?page=admin-events");
            exit;
        }

        if ($this->registration->rejectRegistration($id, $reason ?: null)) {
            $_SESSION['success'] = "Registration rejected.";
        } else {
            $_SESSION['error'] = "Could not reject this registration.";
        }

        header("Location: index.php?page=admin-registrations&event_id=" . $registration['event_id']);
        exit;
    }

    /* ==================================================
        MARK ATTENDANCE
    ================================================== */

    public function markAttendance()
    {
        AuthController::checkAdmin();

        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $registration = $this->registration->getRegistrationById($id);

        if (!$id || !$registration) {
            $_SESSION['error'] = "Registration not found.";
            header("Location: index.php?page=admin-events");
            exit;
        }

        if ($this->registration->markAttendance($id)) {
            $_SESSION['success'] = "Attendance marked.";
        } else {
            $_SESSION['error'] = "Could not mark attendance.";
        }

        header("Location: index.php?page=admin-registrations&event_id=" . $registration['event_id']);
        exit;
    }

    /* ==================================================
        GET REGISTRATIONS FOR AN EVENT (for the admin view)
    ================================================== */

    public function getEventRegistrations($eventId, $status = null)
    {
        return $this->registration->getEventRegistrations($eventId, $status);
    }

    public function getStats($eventId)
    {
        return $this->registration->getRegistrationStats($eventId);
    }
}
