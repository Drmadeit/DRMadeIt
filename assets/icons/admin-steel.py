# Makes the DR Admin home-screen icons (steel blade on black) from assets/img/logo.webp.
# Small sizes drop the thin cabinet lines and thicken the name so it reads at icon size.
# Run from a scratch folder: python3 admin-steel.py, then copy icon-steel-{180,192,512}.png to assets/img/admin-*.png
import sys
from PIL import Image, ImageFilter
import numpy as np
from collections import deque
src=np.array(Image.open('/home/user/DRMadeIt/assets/img/logo.webp').convert('RGB')).astype(float)
H,W,_=src.shape; r,g,b=src[...,0],src[...,1],src[...,2]; lum=src.mean(axis=2)
def flood(seedmask, ok):
    seen=np.zeros((H,W),bool); q=deque(zip(*np.nonzero(seedmask)))
    while q:
        y,x=q.popleft()
        if 0<=y<H and 0<=x<W and not seen[y,x] and ok[y,x]:
            seen[y,x]=True; q.extend([(y+1,x),(y-1,x),(y,x+1),(y,x-1)])
    return seen
edge=np.zeros((H,W),bool); edge[0,:]=edge[-1,:]=edge[:,0]=edge[:,-1]=True
out=flood(edge, lum>140)
grayish=abs(r-b)<45
darkish=(lum<=140)&grayish&~out
# blade = dark pixels touching the outside
touch=darkish&(np.roll(out,1,0)|np.roll(out,-1,0)|np.roll(out,1,1)|np.roll(out,-1,1))
blade=flood(touch, darkish)
red=(r>150)&(g<90)
yy,xx=np.mgrid[0:H,0:W]; cx,cy=W/2,H/2
def build(simple):
    res=src.copy()
    if simple:
        # red disc: paint the thin cabinet lines red, except inside the white name band
        rr=np.hypot(xx-cx,yy-cy); R=np.percentile(rr[red],99.5)
        rows=np.nonzero(((~red)&(lum>200)&(abs(xx-cx)<20)).any(axis=1)&True)[0]
        # band = the widest run of white rows through the center column
        col=(lum[:,int(cx)-3:int(cx)+3].mean(axis=1)>200)&(np.abs(np.arange(H)-cy)<R*0.4)
        band=np.zeros(H,bool); 
        idx=np.nonzero(col)[0]
        if len(idx): band[idx.min():idx.max()+1]=True
        inband=band[:,None]&np.ones((1,W),bool)
        lines=(rr<R-4)&~inband&(r>120)
        res[lines]=[237,28,36]
        # fatten the name letters
        txt=Image.fromarray(((red&inband)*255).astype(np.uint8)).filter(ImageFilter.MaxFilter(7))
        tm=np.array(txt)>0
        res[tm&inband&(rr<R)]=[237,28,36]
    t=((xx-cx)*-0.6+(yy-cy)*-0.8)/(W/2)
    steel=np.clip(150+55*t,95,205)
    a=np.clip((150-lum)/150,0.0,1)[...,None]
    tint=np.stack([steel,steel*1.02,steel*1.07],-1).clip(0,255)
    m=blade[...,None]
    res=np.where(m, a*tint+(1-a)*res, res)
    res[out]=[10,10,11]
    return Image.fromarray(res.astype(np.uint8))
def tile(img,s,scale):
    t=Image.new('RGB',(s,s),(10,10,11)); k=int(s*scale); t.paste(img.resize((k,k),Image.LANCZOS),((s-k)//2,(s-k)//2)); return t
full=build(False); small=build(True)
tile(full,512,.88).save('icon-steel-512.png')
for s in (180,192): tile(small,s,.92).save(f'icon-steel-{s}.png')
tile(small,512,.92).save('icon-steel-small-big.png')
