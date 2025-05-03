<?php
// =================== SETTINGS ===================

// Database credentials
$db_host = 'YOUR_DB_HOST';
$db_name = 'YOUR_DB_NAME';
$db_user = 'YOUR_DB_USER';
$db_pass = 'YOUR_DB_PASSWORD';

// SMTP credentials
$smtp_host = 'YOUR_SMTP_HOST';
$smtp_user = 'YOUR_SMTP_USER';
$smtp_pass = 'YOUR_SMTP_PASSWORD';
$smtp_port = 465; 

// SMTP encryption ('ssl' or 'tls')
$smtp_encryption = 'ssl'; // Change to 'tls' if using port 587

// Your email for BCC + summary reports
$admin_email = 'YOUR_ADMIN_EMAIL';

// Set the reminder_days variable (default 1, change if needed)
$reminder_days = 1;


// ===============================================

// PHPMailer setup
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

date_default_timezone_set('Europe/Dublin'); // Adjust timezone if needed

// Connect to the database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    // In cron, errors won't show — consider logging fatal errors separately
    exit();
}

// Query for selecting recurring invoices that are not deleted
// Only last sent date is available, so we fetch frequencies too
$sql = "
    SELECT 
        i.id AS invoice_id,
        i.last_sent_date,
        i.start_date,
        i.amount,
        c.name AS client_name,
        co.first_name AS contact_first_name,
        co.email AS client_email,
        f.date_interval
    FROM invoices i
    LEFT JOIN clients c ON i.client_id = c.id
    LEFT JOIN contacts co ON co.client_id = c.id AND co.is_primary = 1
    LEFT JOIN frequencies f ON i.frequency_id = f.id
    WHERE i.is_recurring = 1
    AND i.is_deleted = 0
    AND c.deleted_at IS NULL
    AND co.deleted_at IS NULL
";

$result = $conn->query($sql);

// Prepare logging
$log_file = __DIR__ . '/reminder_log.txt';
file_put_contents($log_file, "==== Reminder run on " . date('Y-m-d H:i:s') . " ====\n", FILE_APPEND);

$reminders_sent = 0;
$reminder_details = "";
$invoices_checked = 0;

// Loop through results and send emails
if ($result && $result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {

        $invoices_checked++;

        $client_name = $row['contact_first_name'] ?: $row['client_name']; // fallback
        $client_email = $row['client_email'];
        $amount = number_format($row['amount'], 2);
        $last_sent = $row['last_sent_date'];
        $start_date = $row['start_date'];
        $date_interval = $row['date_interval']; // e.g., "1 year"

        // If no last_sent_date but start_date exists, use start_date as last_sent_date and log it
        if (empty($last_sent) && !empty($start_date)) {
            $log_msg = "Invoice ID {$row['invoice_id']} has no last_sent_date, using start_date $start_date instead.\n";
            file_put_contents($log_file, $log_msg, FILE_APPEND);
            $last_sent = $start_date;
        }

        // Calculate next_send_date in PHP (using $last_sent, possibly replaced above)
        $next_send_date = date('Y-m-d', strtotime($last_sent . ' + ' . $date_interval));

        // Prepare PHPMailer
        $mail = new PHPMailer(true);
        
        // Only send email if it is due in the interval below
        if ($next_send_date == date('Y-m-d', strtotime('+' . $reminder_days . ' days'))) {

            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = $smtp_host;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtp_user;
                $mail->Password   = $smtp_pass;
                $mail->SMTPSecure = ($smtp_encryption == 'ssl') 
                    ? PHPMailer::ENCRYPTION_SMTPS 
                    : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $smtp_port;

                // Recipients
                $mail->setFrom($smtp_user, 'Your_Business_Name');
                $mail->addAddress($client_email, $client_name);
                $mail->addBCC($admin_email, 'Your_Name'); // You get a copy too

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Upcoming Invoice Reminder';
                $mail->Body = "
                    Hi $client_name,<br><br>

                    This is a friendly reminder that your web and/or domain hosting subscription payment of <strong>€$amount</strong> 
                    is scheduled in $reminder_days day" . ($reminder_days > 1 ? "s" : "") . ".<br><br>

                    As this is an automatic renewal, the payment will be charged to your saved card on file on <strong>" . date('j F Y', strtotime($next_send_date)) . "</strong>.<br><br>

                    If you have any questions, feel free to get in touch.<br><br>

                    Kind regards,<br>
                    Your_Name
                ";
                $mail->AltBody = "Hi $client_name, your subscription payment of €$amount is scheduled in $reminder_days day" . ($reminder_days > 1 ? "s" : "") . " and will be charged on " . date('j F Y', strtotime($next_send_date)) . ".";

                $mail->send();

                // Logging
                $log_msg = "Reminder sent to $client_email\n";
                file_put_contents($log_file, $log_msg, FILE_APPEND);
                $reminder_details .= $log_msg;
                $reminders_sent++;

            } catch (Exception $e) {
                $error_msg = "Failed to send to $client_email: {$mail->ErrorInfo}\n";
                file_put_contents($log_file, $error_msg, FILE_APPEND);
                $reminder_details .= $error_msg;
            }
        } // endif check next send date
    }

}

$conn->close();

// =================== SUMMARY EMAIL ===================

$summary = ($reminders_sent > 0)
    ? "Reminders sent: $reminders_sent out of $invoices_checked invoices checked.\n\nDetails:\n$reminder_details"
    : "No reminders sent today. $invoices_checked invoices checked.\n\nChecked on: " . date('Y-m-d H:i:s');

// Send summary email
try {
    $summary_mail = new PHPMailer(true);
    $summary_mail->isSMTP();
    $summary_mail->Host       = $smtp_host;
    $summary_mail->SMTPAuth   = true;
    $summary_mail->Username   = $smtp_user;
    $summary_mail->Password   = $smtp_pass;
    $summary_mail->SMTPSecure = ($smtp_encryption == 'ssl') 
        ? PHPMailer::ENCRYPTION_SMTPS 
        : PHPMailer::ENCRYPTION_STARTTLS;
    $summary_mail->Port       = $smtp_port;

    $summary_mail->setFrom($smtp_user, 'Your_Name');
    $summary_mail->addAddress($admin_email, 'Your_Name');

    $summary_mail->isHTML(false);
    $summary_mail->Subject = 'Daily Invoice Reminder Summary';
    $summary_mail->Body    = $summary;

    $summary_mail->send();

    file_put_contents($log_file, "Summary email sent to $admin_email\n", FILE_APPEND);

} catch (Exception $e) {
    file_put_contents($log_file, "Failed to send summary email: {$summary_mail->ErrorInfo}\n", FILE_APPEND);
}

?>