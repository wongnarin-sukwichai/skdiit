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
                            <p style="margin:0 0 4px;color:#f5b800;font-size:13px;letter-spacing:2px;">SKDIIT 2027</p>

                            {{-- Thai --}}
                            <h1 style="margin:0 0 16px;font-size:20px;color:#fff;">
                                {{ $kind === 'registered' ? 'ลงทะเบียนเรียบร้อยแล้ว' : 'รหัสผ่านใหม่ของคุณ' }}
                            </h1>
                            <p style="margin:0 0 16px;line-height:1.6;">
                                เรียน {{ $user->name }}<br>
                                ใช้รหัสผ่านด้านล่างนี้คู่กับอีเมล {{ $user->email }} เพื่อเข้าสู่ระบบ
                                @if ($kind === 'reset') รหัสผ่านเดิมใช้ไม่ได้แล้ว @endif
                            </p>

                            <p style="margin:24px 0;text-align:center;">
                                <span style="display:inline-block;padding:12px 24px;border-radius:12px;background:#0b0b0b;border:1px solid #f5b800;color:#f5b800;font-size:30px;letter-spacing:10px;font-family:Consolas,monospace;">{{ $password }}</span>
                            </p>

                            <p style="margin:0 0 24px;text-align:center;">
                                <a href="{{ $loginUrl }}" style="display:inline-block;padding:12px 28px;border-radius:8px;background:#f5b800;color:#000;font-weight:bold;text-decoration:none;">
                                    เข้าสู่ระบบ / Sign in
                                </a>
                            </p>

                            {{-- English --}}
                            <hr style="border:none;border-top:1px solid #333;margin:24px 0;">
                            <p style="margin:0 0 16px;line-height:1.6;color:#a3a3a3;font-size:14px;">
                                Dear {{ $user->name }},<br>
                                {{ $kind === 'registered' ? 'Thank you for registering.' : 'Here is your new password.' }}
                                Use the password above with {{ $user->email }} to sign in.
                                @if ($kind === 'reset') Your old password no longer works. @endif
                            </p>
                            <p style="margin:0;color:#737373;font-size:12px;line-height:1.6;">
                                คุณเปลี่ยนรหัสผ่านเองได้ที่เมนู "ข้อมูลของฉัน" หลังเข้าสู่ระบบ · You can change it in "My details" after signing in.<br>
                                หากคุณไม่ได้ลงทะเบียน โปรดเพิกเฉยต่ออีเมลนี้ · If you did not register, please ignore this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
