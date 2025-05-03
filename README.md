# Invoice Ninja Email Reminder Script

## Overview
Invoice Ninja v4 doesn’t have a built-in way to send reminders **before** the next recurring invoice is created. This can lead to clients being surprised by automatic charges.

This PHP script fixes that by automatically sending email reminders a set number of days before a recurring invoice is due. It gives clients a heads-up about the upcoming payment and reduces the chance of failed or disputed auto-bill charges.

### Why is this helpful?

By notifying clients early, it can also prevent unwanted invoices from being created. This is important in countries where deleting invoices isn’t easy because invoice numbers must stay in order. Clients will also appreciate the courtesy of getting a reminder before their card is charged.

## Usage as a Cron Job

This script is meant to run automatically as a daily cron job on the same server where Invoice Ninja v4 is installed (or a server that can access its database).

Example cron entry:
```bash
0 9 * * * /usr/bin/php /path/to/email-reminders.php
```

This runs the script every day at 9:00 AM. Change the time and path as needed. Check with your hosting provider if you’re not sure about the PHP path.

## Compatibility
- Works with Invoice Ninja version 4 only.

## How It Works
- Connects to your Invoice Ninja database to find recurring invoices.
- Since Invoice Ninja v4 doesn’t store the next due date in the database, the script calculates it dynamically for each recurring invoice.
- Sends reminder emails to clients a set number of days before the invoice date.
- Sends a daily summary email to the admin.
- Logs all actions to a local log file.

## PHPMailer
This script uses [PHPMailer](https://github.com/PHPMailer/PHPMailer) to send emails via SMTP.
PHPMailer’s README explains how to use it without Composer and where to download the zip file.
The PHPMailer files should be placed in the same folder as this script, inside a folder named `PHPMailer`.

## Customisation Notes
- Change `$reminder_days` in the script to set how many days before the invoice date to send reminders.
- Edit the email content in `$mail->Body` and `$mail->AltBody` if needed.
- Update SMTP settings (`$smtp_host`, `$smtp_user`, `$smtp_pass`, and encryption type) to match your mail server.
- Set the SMTP encryption type (`ssl` or `tls`) at the top of the script.
- Make sure your database connection settings match your Invoice Ninja setup (you can check them in the `.env` file in your Invoice Ninja root folder).
- Update `$admin_email` to receive BCC copies and the daily summary email.

## Sample Client Email

**Subject:** Upcoming Invoice Reminder

**HTML Body:**
```html
Hi Sarah,<br><br>

This is a friendly reminder that your web and/or domain hosting subscription payment of <strong>€49.99</strong> 
is scheduled in 5 days.<br><br>

As this is an automatic renewal, the payment will be charged to your saved card on file on <strong>10 May 2025</strong>.<br><br>

If you have any questions, feel free to get in touch.<br><br>

Kind regards,<br>
Your_Name
```

**Plain Text Version (AltBody):**
```
Hi Sarah, your subscription payment of €49.99 is scheduled in 5 days and will be charged on 10 May 2025.
```

---

LICENSE
-------
MIT License

Copyright (c) 2024

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.