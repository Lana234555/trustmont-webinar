"""Turn the built (static) index.html into Lana's GitHub-only version with all scroll animations/transitions."""
import re,sys
p=sys.argv[1];s=open(p).read()
R=[('<section class="why-pin static" id="why"','<section class="why-pin is-covered" id="why"'),
   ('<section class="why-pin static" id="why-join"','<section class="why-pin" id="why-join"'),
   ('<div class="panel-wrap" id="prog-wrap">','<div class="panel-wrap overlap-in" id="prog-wrap">'),
   ('<section class="sec" id="speakers"','<section class="sec stick-prev" id="speakers"'),
   ('<div class="panel-wrap" id="aud-wrap">','<div class="panel-wrap overlap-in2" id="aud-wrap">')]
for a,b in R:
    assert s.count(a)==1,a; s=s.replace(a,b)
s,n=re.subn(r'\s*<div class="scroll-cue" aria-hidden="true"><span></span></div>','',s); assert n==4,n
open(p,'w').write(s)
