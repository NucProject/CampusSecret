#!/usr/bin/env python
# -*- coding: utf-8 -*-
import sys
import os
import urllib
import urllib2
import json
import cookielib
import random
import codecs
import hashlib

def md5(d):
	h = hashlib.md5()
	h.update(d)
	return h.hexdigest()

p = os.walk('.').next()

for i in p[2]:
	if i.endswith(".png"):
		n = int(i[:-4])
		v = (n + 360)
		v = "[" + str(v) + ".0]"
		m = md5(v)
		#if n in range(1, 101):
		os.rename("%s.png" % n, m)



