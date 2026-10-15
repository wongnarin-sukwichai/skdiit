<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;background:#0b0b0b;font-family:Tahoma,Arial,sans-serif;color:#e5e5e5;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#161616;border:1px solid #5c4a14;border-radius:16px;padding:32px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 16px;color:#f5b800;font-size:13px;letter-spacing:2px;">{{ $shortName }}</p>

                            {{-- Thai --}}
                            <p style="margin:0 0 16px;line-height:1.7;white-space:pre-line;">{{ $bodyTh }}</p>

                            @if ($buttonUrl)
                                <p style="margin:24px 0;text-align:center;">
                                    <a href="{{ $buttonUrl }}" style="display:inline-block;padding:12px 28px;border-radius:8px;background:#f5b800;color:#000;font-weight:bold;text-decoration:none;">
                                        เข้าสู่ระบบ / Open the system
                                    </a>
                                </p>
                            @endif

                            {{-- English --}}
                            @if ($bodyEn)
                                <hr style="border:none;border-top:1px solid #333;margin:24px 0;">
                                <p style="margin:0;line-height:1.7;color:#a3a3a3;font-size:14px;white-space:pre-line;">{{ $bodyEn }}</p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
