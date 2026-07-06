<?php

namespace App\Services;

class SharedDocumentMailBuilder
{
    public static function subject(
        string $fileName,
        ?string $referenceNo = null,
        ?string $projectNumber = null
    ): string {
        $parts = ['Tanseeq DMS — Shared Document'];

        if ($referenceNo !== null && $referenceNo !== '' && $referenceNo !== '—') {
            $parts[] = $referenceNo;
        }

        if ($projectNumber !== null && $projectNumber !== '' && $projectNumber !== '-') {
            $parts[] = $projectNumber;
        }

        $parts[] = $fileName;

        return implode(' | ', $parts);
    }

    /**
     * @param  array{
     *     recipient: string,
     *     senderName: string,
     *     senderEmail: string,
     *     fileName: string,
     *     referenceNo?: string|null,
     *     documentSubject?: string|null,
     *     projectNumber?: string|null,
     *     projectName?: string|null,
     *     entityName?: string|null,
     *     folderLabel?: string|null,
     *     personalMessage?: string|null,
     * }  $data
     */
    public static function htmlBody(array $data): string
    {
        $recipient = self::escape($data['recipient']);
        $senderName = self::escape($data['senderName']);
        $senderEmail = self::escape($data['senderEmail']);
        $fileName = self::escape($data['fileName']);
        $referenceNo = self::display($data['referenceNo'] ?? null);
        $documentSubject = self::display($data['documentSubject'] ?? null);
        $projectNumber = self::display($data['projectNumber'] ?? null);
        $projectName = self::display($data['projectName'] ?? null);
        $entityName = self::display($data['entityName'] ?? null);
        $folderLabel = self::display($data['folderLabel'] ?? null);
        $personalMessage = trim((string) ($data['personalMessage'] ?? ''));

        $greeting = 'Hello,';
        $personalBlock = '';
        if ($personalMessage !== '') {
            $personalBlock = '<p style="margin:0 0 18px; color:#334155; line-height:1.6;">'
                .nl2br(self::escape($personalMessage))
                .'</p>';
        }

        $rows = [
            'File name' => $fileName,
            'Reference no.' => $referenceNo,
            'Subject' => $documentSubject,
            'Project no.' => $projectNumber,
            'Project name' => $projectName,
            'Entity' => $entityName,
            'Folder' => $folderLabel,
        ];

        $detailRows = '';
        foreach ($rows as $label => $value) {
            if ($value === '—') {
                continue;
            }
            $detailRows .= '<tr>'
                .'<td style="padding:10px 14px; border-bottom:1px solid #e2e8f0; color:#64748b; width:34%; font-size:14px;">'
                .self::escape($label)
                .'</td>'
                .'<td style="padding:10px 14px; border-bottom:1px solid #e2e8f0; color:#1e293b; font-size:14px;">'
                .$value
                .'</td>'
                .'</tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family:'Segoe UI', Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#212d3e; padding:24px 28px;">
                            <p style="margin:0 0 6px; color:#c4a47c; font-size:12px; letter-spacing:0.14em; text-transform:uppercase;">Tanseeq Investment</p>
                            <h1 style="margin:0; color:#ffffff; font-size:22px; font-weight:600;">Document shared with you</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 14px; color:#1e293b; font-size:15px; line-height:1.6;">{$greeting}</p>
                            <p style="margin:0 0 18px; color:#334155; font-size:15px; line-height:1.6;">
                                <strong>{$senderName}</strong> has shared a document with you through the
                                <strong>Tanseeq Document Management System</strong>.
                                The file is attached to this email.
                            </p>
                            {$personalBlock}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; margin:0 0 22px;">
                                <tr>
                                    <td colspan="2" style="background:#f8fafc; padding:12px 14px; color:#212d3e; font-size:14px; font-weight:600; border-bottom:1px solid #e2e8f0;">
                                        Document details
                                    </td>
                                </tr>
                                {$detailRows}
                            </table>
                            <p style="margin:0 0 8px; color:#64748b; font-size:13px; line-height:1.6;">
                                If you were not expecting this email, please contact the sender or your system administrator.
                            </p>
                            <p style="margin:0; color:#1e293b; font-size:14px; line-height:1.6;">
                                Kind regards,<br>
                                <strong>{$senderName}</strong><br>
                                <span style="color:#64748b;">{$senderEmail}</span><br>
                                <span style="color:#64748b;">Tanseeq Document Management System</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

  public static function plainTextBody(array $data): string
    {
        $senderName = (string) ($data['senderName'] ?? '');
        $fileName = (string) ($data['fileName'] ?? '');
        $referenceNo = self::display($data['referenceNo'] ?? null);
        $documentSubject = self::display($data['documentSubject'] ?? null);
        $projectNumber = self::display($data['projectNumber'] ?? null);
        $projectName = self::display($data['projectName'] ?? null);
        $entityName = self::display($data['entityName'] ?? null);
        $folderLabel = self::display($data['folderLabel'] ?? null);
        $personalMessage = trim((string) ($data['personalMessage'] ?? ''));

        $lines = [
            'Hello,',
            '',
            $senderName.' has shared a document with you from Tanseeq Document Management System.',
            'The file is attached to this email.',
            '',
        ];

        if ($personalMessage !== '') {
            $lines[] = $personalMessage;
            $lines[] = '';
        }

        $lines[] = 'Document details';
        $lines[] = '----------------';
        $lines[] = 'File name: '.$fileName;
        if ($referenceNo !== '—') {
            $lines[] = 'Reference no.: '.$referenceNo;
        }
        if ($documentSubject !== '—') {
            $lines[] = 'Subject: '.$documentSubject;
        }
        if ($projectNumber !== '—') {
            $lines[] = 'Project no.: '.$projectNumber;
        }
        if ($projectName !== '—') {
            $lines[] = 'Project name: '.$projectName;
        }
        if ($entityName !== '—') {
            $lines[] = 'Entity: '.$entityName;
        }
        if ($folderLabel !== '—') {
            $lines[] = 'Folder: '.$folderLabel;
        }

        $lines[] = '';
        $lines[] = 'Kind regards,';
        $lines[] = $senderName;

        return implode("\n", $lines);
    }

    private static function display(?string $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? self::escape($value) : '—';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
