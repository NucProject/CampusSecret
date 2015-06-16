#!/usr/bin/env python
# -*- coding: utf-8 -*-

__author__ = 'healer'
import sys
import os
import urllib
import urllib2
import json
import cookielib
import random
import codecs
from secretclient import *


host1 = "127.0.0.1"				# Localhost
host2 = "182.92.80.195"			# Aliyun
console = False

host = host1
if len(sys.argv) > 1:
	console = True
	if sys.argv[1] == "2":
		host = host2
	else:
		host = host1

print "HOST: %s" % host


sc = SecretClient(host)
r, e = sc.fetch_verifycode("12345678900")
# The Phone Number should exist already
assert(r == 1001)

# Is a fake number, is supposed no one used before
phone = "92345678900"
sc.delcachevalue("u:v:code:%s" % phone)
sc.delcachevalue("u:v:ctrl:%s" % phone)

r, e = sc.fetch_verifycode(phone)
print r
assert(r['verify'] == 'Sent')

r, e = sc.fetch_verifycode(phone)
# Sent twice!
assert(r == 1005)