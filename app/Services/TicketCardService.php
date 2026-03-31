<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TicketIssued;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Common\EccLevel;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketCardService
{
    // Portrait canvas (5:9, matching uploaded ticket image ratio 200×360)
    private const W = 1080;
    private const H = 1944;

    // Overlay bar height — starts at y=278 on the canvas
    private const BAR_H = 1666;

    // QR rendered size on card
    private const QR_SIZE = 410;

    // Padding inside bar
    private const PAD = 36;

    // Font path candidates
    private array $fontPaths = [];

    public function __construct()
    {
        $this->fontPaths = [
            base_path('resources/fonts/arial.ttf'),
            'C:/Windows/Fonts/arial.ttf',
            '/usr/share/fonts/truetype/msttcorefonts/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        ];
    }

    /**
     * Generate the ticket card image and save it.
     * Returns the stored path (relative to public disk).
     */
    public function generate(TicketIssued $issued, Order $order): string
    {
        $canvas = imagecreatetruecolor(self::W, self::H);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        // ── Background ──────────────────────────────────────────────────────
        $this->drawBackground($canvas, $issued);

        // ── Dark overlay bar at bottom ──────────────────────────────────────
        $this->drawBar($canvas);

        // ── QR code (lower-right of bar) ───────────────────────────────────
        $qrTopY = self::H - self::PAD - self::QR_SIZE;
        $qrImg  = $this->generateQrImage($issued->qr_code);
        if ($qrImg) {
            $qrX = self::W - self::PAD - self::QR_SIZE;
            imagecopyresampled(
                $canvas, $qrImg,
                (int)$qrX, (int)$qrTopY,
                0, 0,
                self::QR_SIZE, self::QR_SIZE,
                imagesx($qrImg), imagesy($qrImg)
            );
            imagedestroy($qrImg);
        }

        // ── Text info (left side, top-aligned to QR top) ──────────────────
        $this->drawInfo($canvas, $issued, $order, isset($qrImg), $qrTopY);

        // ── Save and return path ───────────────────────────────────────────
        $path = 'ticket-cards/' . Str::uuid() . '.jpg';
        Storage::disk('public')->makeDirectory('ticket-cards');

        ob_start();
        imagejpeg($canvas, null, 88);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);

        Storage::disk('public')->put($path, $jpeg);

        return $path;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function drawBackground(GdImage $canvas, TicketIssued $issued): void
    {
        $bgUrl = $issued->ticket?->ticket_image_url
            ?? $issued->ticket?->event?->cover_url
            ?? null;

        $bgLoaded = false;

        if ($bgUrl) {
            try {
                $context = stream_context_create(['http' => ['timeout' => 5]]);
                $data    = @file_get_contents($bgUrl, false, $context);

                if ($data) {
                    $src = @imagecreatefromstring($data);
                    if ($src) {
                        // Scale & crop to fill canvas
                        $sw = imagesx($src);
                        $sh = imagesy($src);

                        $scale = max(self::W / $sw, self::H / $sh);
                        $dw    = (int)($sw * $scale);
                        $dh    = (int)($sh * $scale);
                        $ox    = (int)(($dw - self::W) / 2);
                        $oy    = (int)(($dh - self::H) / 2);

                        $tmp = imagecreatetruecolor($dw, $dh);
                        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);

                        imagecopy($canvas, $tmp, 0, 0, $ox, $oy, self::W, self::H);

                        // Darken background so text is legible
                        $overlay = imagecreatetruecolor(self::W, self::H);
                        $dark    = imagecolorallocatealpha($overlay, 0, 0, 0, 100);
                        imagefill($overlay, 0, 0, $dark);
                        imagealphablending($canvas, true);
                        imagecopy($canvas, $overlay, 0, 0, 0, 0, self::W, self::H);

                        imagedestroy($tmp);
                        imagedestroy($src);
                        imagedestroy($overlay);

                        $bgLoaded = true;
                    }
                }
            } catch (\Throwable) {
                // fall through to gradient
            }
        }

        if (! $bgLoaded) {
            $this->drawGradient($canvas);
        }
    }

    private function drawGradient(GdImage $canvas): void
    {
        // Dark blue → dark purple gradient
        for ($y = 0; $y < self::H; $y++) {
            $r = (int)(26  + (45  - 26)  * $y / self::H);
            $g = (int)(26  + (14  - 26)  * $y / self::H);
            $b = (int)(46  + (82  - 46)  * $y / self::H);
            $c = imagecolorallocate($canvas, $r, $g, $b);
            imagefilledrectangle($canvas, 0, $y, self::W - 1, $y, $c);
        }
    }

    private function drawBar(GdImage $canvas): void
    {
        // Gradient fade: transparent at top → dark at bottom, covering only the text area
        $fadeH = 520;
        $startY = self::H - $fadeH;

        for ($y = 0; $y < $fadeH; $y++) {
            $alpha = (int)(127 - ($y / $fadeH) * 60); // 127=fully transparent → 67=light dark
            $color = imagecolorallocatealpha($canvas, 5, 5, 20, $alpha);
            imagefilledrectangle($canvas, 0, $startY + $y, self::W - 1, $startY + $y, $color);
        }
    }

    private function generateQrImage(string $data): ?GdImage
    {
        try {
            $options = new QROptions([
                'outputInterface'  => QRGdImagePNG::class,
                'returnResource'   => true,
                'eccLevel'         => EccLevel::M, // 15% recovery — good for printed tickets
                'scale'            => 12,
                'imageTransparent' => false,
                'addQuietzone'     => true,
                'quietzoneSize'    => 4,           // standard recommended quiet zone
            ]);

            $result = (new QRCode($options))->render($data);

            if ($result instanceof GdImage) {
                // Add a solid white border (8px each side) so QR never bleeds into background
                $qrW    = imagesx($result);
                $qrH    = imagesy($result);
                $border = 16;
                $padded = imagecreatetruecolor($qrW + $border * 2, $qrH + $border * 2);
                $white  = imagecolorallocate($padded, 255, 255, 255);
                imagefill($padded, 0, 0, $white);
                imagecopy($padded, $result, $border, $border, 0, 0, $qrW, $qrH);
                imagedestroy($result);
                return $padded;
            }
        } catch (\Throwable) {
            // ignore
        }

        return null;
    }

    private function drawInfo(GdImage $canvas, TicketIssued $issued, Order $order, bool $hasQr, int $qrTopY): void
    {
        $font      = $this->resolveFont();
        $blackFont = $this->resolveBlackFont();

        $x = self::PAD + 8;

        // Colors
        $white  = imagecolorallocate($canvas, 255, 255, 255);
        $gold   = imagecolorallocate($canvas, 212, 175, 55);
        $silver = imagecolorallocate($canvas, 180, 180, 200);

        // Build lines top-down, starting at qrTopY
        $lineH = 8; // gap between lines

        // ── Label: eTICKET - 1 ENTRY  (Arial Black)
        $yLabel = $qrTopY + 32;
        if ($blackFont) {
            imagettftext($canvas, 28, 0, $x, $yLabel, $white, $blackFont, 'eTICKET - 1 ENTRY');
        } else {
            imagestring($canvas, 4, $x, $yLabel - 28, 'eTICKET - 1 ENTRY', $white);
        }

        // ── Line 1: <Event> - <Ticket Tier>  (large gold)
        $event     = $issued->ticket?->event;
        $eventName = $event?->name ?? '';
        $tierName  = $issued->ticket?->name ?? 'General Admission';
        $line1     = $eventName ? $eventName . ' - ' . $tierName : $tierName;
        $y1 = $yLabel + 32 + $lineH + 28;
        if ($font) {
            imagettftext($canvas, 28, 0, $x, $y1, $gold, $font, $line1);
        } else {
            imagestring($canvas, 5, $x, $y1 - 28, $line1, $gold);
        }

        // ── Line 2: <Ticket No>  (extra large white)
        $ticketNo = strtoupper(substr($issued->qr_code, -12));
        $y2 = $y1 + 28 + $lineH + 44;
        if ($font) {
            imagettftext($canvas, 52, 0, $x, $y2, $white, $font, $ticketNo);
        } else {
            imagestring($canvas, 5, $x, $y2 - 52, $ticketNo, $white);
        }

        // ── Line 3: <Email>
        $email = $issued->holder_email ?? $order->email ?? '';
        $y3 = $y2 + 52 + $lineH + 10;
        if ($font) {
            imagettftext($canvas, 22, 0, $x, $y3, $silver, $font, $email);
        } else {
            imagestring($canvas, 3, $x, $y3 - 22, $email, $silver);
        }

        // ── Line 4: <Issued date> · <Ref>
        $issued_date = $order->created_at?->format('M j, Y') ?? now()->format('M j, Y');
        $line4       = $issued_date . '  ·  ' . $order->reference;
        $y4 = $y3 + 22 + $lineH + 6;
        if ($font) {
            imagettftext($canvas, 22, 0, $x, $y4, $silver, $font, $line4);
        } else {
            imagestring($canvas, 2, $x, $y4 - 22, $line4, $silver);
        }

        // Watermark: "VALID" rotated, semi-transparent
        if ($font) {
            $watermark = imagecolorallocatealpha($canvas, 255, 255, 255, 100);
            imagettftext($canvas, 54, -30, self::W - 300, $qrTopY - 40, $watermark, $font, 'VALID');
        }
    }

    private function resolveFont(): ?string
    {
        foreach ($this->fontPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }

    private function resolveBlackFont(): ?string
    {
        $blackPaths = [
            base_path('resources/fonts/ariblk.ttf'),
            'C:/Windows/Fonts/ariblk.ttf',
            '/usr/share/fonts/truetype/msttcorefonts/Arial_Black.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        ];
        foreach ($blackPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return $this->resolveFont();
    }
}
