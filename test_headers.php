<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Mail\SadminEnquiryMail;

$data = [
    'first_name' => 'Kim',
    'last_name' => 'Minjun',
    'email' => 'customer@example.com',
    'phone' => '010-1234-5678',
    'message' => 'Header test',
];

$mail = new SadminEnquiryMail('emails.sadmin_enquiry', 'Test Subject', $data);
$msg = $mail->build();

// Check symfony message headers
$sym = $mail->buildSymfonyMessage();
echo $sym->getHeaders()->toString();
