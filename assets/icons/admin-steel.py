# Makes the DR Admin home-screen icons (steel blade on black) from assets/img/logo.webp.
# Door/drawer gaps are black; small sizes thicken them and the name so it reads at icon size.
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
    # red disc: the door/drawer gaps (thin white lines) turn black; small sizes get them thicker
    rr=np.hypot(xx-cx,yy-cy); R=np.percentile(rr[red],99.5)
    span=slice(int(cx-R*0.3),int(cx+R*0.3))  # the name band: rows that are mostly white across the middle
    col=((lum[:,span]>200).mean(axis=1)>0.5)&(np.abs(np.arange(H)-cy)<R*0.4)
    band=np.zeros(H,bool); idx=np.nonzero(col)[0]
    if len(idx): band[idx.min():idx.max()+1]=True
    inband=band[:,None]&np.ones((1,W),bool)
    disc=(rr<R-4)&~inband
    a=np.where(disc&(r>120), np.clip((g-40)/160,0,1), 0)  # how "white" each pixel is
    am=Image.fromarray((a*255).astype(np.uint8))
    if simple:
        am=am.filter(ImageFilter.MaxFilter(5))
    a=(np.array(am)/255.0)*disc
    res=np.where(disc[...,None], np.array([237,28,36])*(1-a[...,None])+np.array([12,12,13])*a[...,None], res)
    if simple:
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
