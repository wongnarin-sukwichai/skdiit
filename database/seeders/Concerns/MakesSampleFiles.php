<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Small real files for sample papers, stored on the private disk like uploads (DemoSubmissionSeeder, JournalSampleSeeder). */
trait MakesSampleFiles
{
    /** A one-page PDF stored like an uploaded file. */
    protected function pdf(string $prefix, string $title, string $tag = 'demo'): array
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], 'SKDIIT 2027 sample: '.$title);
        $stream = "BT /F1 14 Tf 60 780 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        $path = "submissions/{$tag}-".$prefix.'-'.substr(md5($title), 0, 8).'.pdf';
        Storage::put($path, $pdf);

        return ['file_path' => $path, 'file_name' => Str::slug(Str::limit($title, 40, '')).'.pdf', 'file_size' => strlen($pdf)];
    }

    /** A minimal one-paragraph Word document stored like an uploaded file. */
    protected function docx(string $prefix, string $title, string $tag = 'demo'): array
    {
        $text = htmlspecialchars('SKDIIT 2027 sample: '.$title, ENT_XML1);
        $parts = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.$text.'</w:t></w:r></w:p></w:body></w:document>',
        ];
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($parts as $name => $xml) {
            $zip->addFromString($name, $xml);
        }
        $zip->close();

        $path = "submissions/word/{$tag}-".$prefix.'-'.substr(md5($title), 0, 8).'.docx';
        Storage::put($path, file_get_contents($tmp));
        $size = filesize($tmp);
        unlink($tmp);

        return ['file_path' => $path, 'file_name' => Str::slug(Str::limit($title, 40, '')).'.docx', 'file_size' => $size];
    }
}
