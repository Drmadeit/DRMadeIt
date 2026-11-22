# Makes assets/img/hero-engraved.jpg: the logo carved straight into wood (the home page header).
# Run: python3 assets/icons/engrave-header.py  (paths at the top point at the source photo and logo)
from PIL import Image, ImageChops, ImageFilter, ImageEnhance, ImageOps
import numpy as np

UP = '/root/.claude/uploads/084d05ce-c75a-5e63-a7b3-bc33959bb456/'
W, H = 1200, 560          # header image size (2x for phones)
LOGO = 470                # logo diameter in the image

# --- Wood: two engraving-free strips from the bottom of the photo, side by side, upscaled ---
photo = Image.open(UP + '6b4b9655-image.png').convert('RGB')
left = photo.crop((0, 1140, 640, 1414))      # clean wood, no engraving
strip = Image.new('RGB', (1280, 274))
strip.paste(left, (0, 0))
strip.paste(ImageOps.mirror(left), (640, 0))  # bookmatched, like a split board opened up
wood = strip.resize((int(1280 * H / 274), H), Image.LANCZOS)
x0 = (wood.width - W) // 2
wood = wood.crop((x0, 0, x0 + W, H))
wood = ImageEnhance.Contrast(wood).enhance(1.08)

# --- Logo masks: black parts carved deep, red parts carved shallow, white stays raised ---
logo = Image.open(UP + 'b13f2c12-image.jpg').convert('RGB').resize((LOGO, LOGO), Image.LANCZOS)
a = np.asarray(logo).astype(int)
r, g, b = a[..., 0], a[..., 1], a[..., 2]
black = ((r < 90) & (g < 90) & (b < 90)).astype(np.uint8) * 255
red = ((r > 150) & (g < 110) & (b < 110)).astype(np.uint8) * 255

def place(mask):
    m = Image.new('L', (W, H), 0)
    m.paste(Image.fromarray(mask).filter(ImageFilter.GaussianBlur(0.6)), ((W - LOGO) // 2, (H - LOGO) // 2 - 34))  # a bit high: the arch covers the bottom
    return m

deep, shallow = place(black), place(red)

def carve(base, mask, depth, shadow):
    """Darken inside the mask, add a shadow on the top-left inner edge and a light rim bottom-right."""
    m = np.asarray(mask).astype(float) / 255
    sh = np.asarray(ImageChops.offset(mask, shadow, shadow)).astype(float) / 255
    inner_shadow = np.clip(m - sh, 0, 1)        # top/left walls of the cut, in shade
    hi = np.asarray(ImageChops.offset(mask, -2, -2)).astype(float) / 255
    rim = np.clip(m - hi, 0, 1)                  # bottom/right walls, catching light
    arr = np.asarray(base).astype(float)
    k = 1 - m * depth - inner_shadow * 0.30 + rim * 0.18
    out = arr * k[..., None]
    # carved floor is a little warmer/rougher
    out[..., 2] *= 1 - m * 0.06
    return Image.fromarray(np.clip(out, 0, 255).astype(np.uint8))

img = carve(wood, shallow, 0.22, 3)
img = carve(img, deep, 0.55, 5)
img.save('/home/user/DRMadeIt/assets/img/hero-engraved.jpg', quality=86)
img.resize((W // 2, H // 2), Image.LANCZOS).save('engraved-small.png')
print('ok', img.size)
