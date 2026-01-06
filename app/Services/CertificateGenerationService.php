<?php

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Exception;

class CertificateGenerationService
{
    /**
     * Generate a PDF certificate for the given certificate record
     *
     * @param Certificate $certificate
     * @return string Path to the generated PDF file
     * @throws Exception
     */
    public function generateCertificate(Certificate $certificate): string
    {
        try {
            $certificate->load(['award', 'student']);

            // Generate HTML content for the certificate
            $html = $this->generateCertificateHTML($certificate);

            // Check if DomPDF is installed
            if (!class_exists('Barryvdh\DomPDF\Facade\Pdf')) {
                throw new Exception('DomPDF package is not installed. Please run: composer require barryvdh/laravel-dompdf');
            }

            // Generate PDF using DomPDF
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('a4', 'landscape');
            $pdf->setOption('enable-local-file-access', true);

            // Save PDF to storage
            $filename = "certificates/{$certificate->certificate_number}.pdf";
            Storage::put($filename, $pdf->output());

            return $filename;
        } catch (Exception $e) {
            throw new Exception("Failed to generate certificate: {$e->getMessage()}");
        }
    }

    /**
     * Generate HTML content for the certificate
     *
     * @param Certificate $certificate
     * @return string
     */
    protected function generateCertificateHTML(Certificate $certificate): string
    {
        $student = $certificate->student;
        $award = $certificate->award;

        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Achievement</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Times New Roman", serif;
            margin: 0;
            padding: 0;
            width: 11.69in;
            height: 8.27in;
            overflow: hidden;
            page-break-after: avoid;
            page-break-inside: avoid;
        }
        .certificate-container {
            width: 11.69in;
            height: 8.27in;
            margin: 0;
            padding: 0;
            background: white;
            position: relative;
            border: 15px solid #d4af37;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .certificate-header {
            text-align: center;
            padding: 20px 20px 12px;
            border-bottom: 2px solid #d4af37;
            flex-shrink: 0;
        }
        .certificate-header h1 {
            font-size: 40px;
            color: #2c3e50;
            margin: 0;
            padding: 0;
            font-weight: bold;
            letter-spacing: 2px;
            line-height: 1.1;
        }
        .certificate-body {
            padding: 18px 80px 12px;
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 0;
        }
        .certificate-text {
            font-size: 20px;
            line-height: 1.1;
            color: #34495e;
            margin: 0;
            padding: 0;
            margin-bottom: 5px;
        }
        .student-name {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin: 5px 0;
            padding: 0;
            text-decoration: underline;
            text-decoration-color: #d4af37;
            text-underline-offset: 5px;
            line-height: 1.1;
        }
        .award-name {
            font-size: 26px;
            color: #d4af37;
            font-weight: bold;
            margin: 5px 0;
            padding: 0;
            line-height: 1.1;
        }
        .award-description {
            font-size: 16px;
            color: #34495e;
            margin: 5px 0 0 0;
            padding: 0;
            line-height: 1.2;
            max-height: 45px;
            overflow: hidden;
        }
        .achievement-details {
            font-size: 16px;
            color: #34495e;
            margin: 4px 0 0 0;
            padding: 0;
            font-style: italic;
            line-height: 1.2;
            max-height: 35px;
            overflow: hidden;
        }
        .issued-date {
            font-size: 18px;
            color: #34495e;
            margin: 8px 0 0 0;
            padding: 0;
            line-height: 1.1;
        }
        .certificate-footer {
            padding: 12px 80px 15px;
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            flex-shrink: 0;
            border-top: 1px solid #e0e0e0;
        }
        .signature {
            text-align: center;
            width: 180px;
        }
        .signature-line {
            border-top: 2px solid #2c3e50;
            margin-top: 30px;
            width: 180px;
        }
        .signature-label {
            margin-top: 6px;
            font-size: 14px;
            color: #34495e;
            line-height: 1.2;
        }
        .certificate-number {
            position: absolute;
            bottom: 12px;
            right: 25px;
            font-size: 11px;
            color: #7f8c8d;
            line-height: 1.2;
        }
        .decoration {
            position: absolute;
            width: 70px;
            height: 70px;
            border: 2px solid #d4af37;
            border-radius: 50%;
            opacity: 0.2;
        }
        .decoration-top-left {
            top: 25px;
            left: 25px;
        }
        .decoration-top-right {
            top: 25px;
            right: 25px;
        }
        .decoration-bottom-left {
            bottom: 25px;
            left: 25px;
        }
        .decoration-bottom-right {
            bottom: 25px;
            right: 25px;
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="decoration decoration-top-left"></div>
        <div class="decoration decoration-top-right"></div>
        <div class="decoration decoration-bottom-left"></div>
        <div class="decoration decoration-bottom-right"></div>
        
        <div class="certificate-header">
            <h1>CERTIFICATE OF ACHIEVEMENT</h1>
        </div>
        
        <div class="certificate-body">
            <div class="certificate-text">
                This is to certify that
            </div>
            
            <div class="student-name">
                ' . htmlspecialchars($student->full_name) . '
            </div>
            
            <div class="certificate-text">
                has successfully completed and achieved excellence in
            </div>
            
            <div class="award-name">
                ' . htmlspecialchars($award->name) . '
            </div>
            
            ' . ($award->description ? '<div class="award-description">' . htmlspecialchars($award->description) . '</div>' : '') . '
            
            ' . ($certificate->achievement_details ? '<div class="achievement-details">' . htmlspecialchars($certificate->achievement_details) . '</div>' : '') . '
            
            <div class="issued-date">
                Issued on ' . $certificate->issued_date->format('F d, Y') . '
            </div>
        </div>
        
        <div class="certificate-footer">
            <div class="signature">
                <div class="signature-line"></div>
                <div class="signature-label">Authorized Signature</div>
            </div>
            <div class="signature">
                <div class="signature-line"></div>
                <div class="signature-label">Date</div>
            </div>
        </div>
        
        <div class="certificate-number">
            Certificate No: ' . htmlspecialchars($certificate->certificate_number) . '
        </div>
    </div>
</body>
</html>';

        return $html;
    }
}
