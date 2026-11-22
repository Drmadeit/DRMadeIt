# Builds assets/img/hero-engraved.jpg (home page header) from the straight-on engraved logo photo. Set SRC, then: python3 assets/icons/header-from-photo.py
# Header from David's straight-on engraved photo: crop around the logo and widen the board by
# mirroring plain wood from beyond the saw teeth (vertical grain, so the seams don't show).
from PIL import Image, ImageOps

SRC = '/root/.claude/uploads/084d05ce-c75a-5e63-a7b3-bc33959bb456/e77d6d05-image.jpg'
im = Image.open(SRC).convert('RGB')               # 1374 x 2048; logo is about x 210-1167, y 568-1454
top, bottom = 505, 1545                           # extra room at the bottom for the arch
band = im.crop((0, top, im.width, bottom))
ASPECT = 390 / 190                                # the header box on a phone
need = round(band.height * ASPECT)
pad = (need - band.width) // 2

def side(strip, width):
    """Plain wood beside the logo: mirror, then normal, then mirror… so every seam matches."""
    out = Image.new('RGB', (width, strip.height))
    x, flip = 0, True
    while x < width:
        piece = ImageOps.mirror(strip) if flip else strip
        out.paste(piece, (x, 0))
        x += strip.width
        flip = not flip
    return out

left_strip = band.crop((0, 0, 190, band.height))                          # left of the teeth
right_strip = band.crop((band.width - 190, 0, band.width, band.height))   # right of the teeth
left = ImageOps.mirror(side(ImageOps.mirror(left_strip), pad))            # grows outward to the left
right = side(right_strip, pad)
wide = Image.new('RGB', (band.width + 2 * pad, band.height))
wide.paste(left, (0, 0))
wide.paste(band, (pad, 0))
wide.paste(right, (pad + band.width, 0))
out = wide.resize((1400, round(1400 / ASPECT)), Image.LANCZOS)
out.save('/home/user/DRMadeIt/assets/img/hero-engraved.jpg', quality=84, optimize=True, progressive=True)
print(wide.size, '->', out.size)
