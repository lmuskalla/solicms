<?php
// Extract 48x48 BMP from ICO, decode to PNG with GD, analyze colors
$ico = file_get_contents('resources/themes/tabubruch/assets/images/favicon.ico');
$count = unpack('v', substr($ico,4,2))[1];
for ($i=0; $i<$count; $i++) {
  $e = unpack('Cw/Ch/Ccolors/Creserved/vplanes/vbpp/Vsize/Voffset', substr($ico, 6+16*$i, 16));
  $w = $e['w'] ?: 256; $h = $e['h'] ?: 256;
  if ($w !== 48) continue;
  $data = substr($ico, $e['offset'], $e['size']);
  file_put_contents('.tmp-48.bmp', $data);
  $im = @imagecreatefrombmp('.tmp-48.bmp');
  if (!$im) { echo "GD can't read BMP directly\n"; 
    // manual decode: BITMAPINFOHEADER, 32bpp BGRA
    $bih = unpack('Vsize/Vwidth/Vheight/vplanes/vbpp/Vcompression/VsizeImage', substr($data, 14, 20));
    echo "  width={$bih['width']} height={$bih['height']} bpp={$bih['bpp']} compression={$bih['compression']}\n";
    $bw = $bih['width']; $bh = abs($bih['height']);
    $rowSize = intdiv(($bw * $bih['bpp'] + 31), 32) * 4;
    $pxStart = 14 + 40;
    $im2 = imagecreatetruecolor($bw, $bh);
    imagealphablending($im2, false); imagesavealpha($im2, true);
    for ($y=0; $y<$bh; $y++) {
      $srcY = $bih['height'] > 0 ? $bh - 1 - $y : $y; // bottom-up if positive height
      $row = substr($data, $pxStart + $srcY*$rowSize, $rowSize);
      for ($x=0; $x<$bw; $x++) {
        $b = ord($row[$x*4]); $g = ord($row[$x*4+1]); $r = ord($row[$x*4+2]); $a = ord($row[$x*4+3]);
        $col = imagecolorallocatealpha($im2, $r, $g, $b, 127 - intdiv($a*127, 255));
        imagesetpixel($im2, $x, $y, $col);
      }
    }
    $im = $im2;
  }
  imagepng($im, '.tmp-tabubruch-48.png');
  // analyze colors incl. transparency
  $w2 = imagesx($im); $h2 = imagesy($im);
  $minX=$w2; $minY=$h2; $maxX=0; $maxY=0;
  $colors = [];
  for ($y=0; $y<$h2; $y++) for ($x=0; $x<$w2; $x++) {
    $rgb = imagecolorat($im, $x, $y);
    $a = ($rgb >> 24) & 0x7F;
    $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;
    if ($a > 60) continue; // skip transparent
    $minX=min($minX,$x); $maxX=max($maxX,$x); $minY=min($minY,$y); $maxY=max($maxY,$y);
    $key = (int)($r/32).','.(int)($g/32).','.(int)($b/32);
    $colors[$key] = ($colors[$key] ?? 0) + 1;
  }
  printf("48x48 bbox: x[$minX..$maxX] y[$minY..$maxY]\n");
  arsort($colors);
  foreach (array_slice($colors, 0, 10, true) as $k => $c) {
    [$r,$g,$b] = array_map(fn($v)=>$v*32+16, explode(',',$k));
    printf("  #%02x%02x%02x  %d\n", $r,$g,$b,$c);
  }
  break;
}
