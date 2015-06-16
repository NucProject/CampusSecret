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
import string
from datetime import *
from secretclient import *
import  time  

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
########################################################################

secret_id = -1
ucode = "";

def timediff():
	global b
	e = time.time()
	print "T", (e - b)
	b = e
b = time.time()

sc1 = SecretClient(host)
r, e = sc1.signin("12345678900", "123456")



c1 = "发条秘密等赞和评论 @ %s" % datetime.utcnow()
r, e = sc1.post_secret(c1, 2)
if not e:
	secret_id = r['secret_id']
	ucode = r['ucode']
	print "The secret: [", secret_id, "]", ucode

sc1.fetch_comments(secret_id)
r, e = sc1.fetch_notice()
if not e:
	print r

sc2 = SecretClient(host)
sc2.signin("13900000001", "123456")
sc2.like_secret(secret_id, ucode)
sc2.fetch_comments(secret_id)


sc3 = SecretClient(host)
sc3.signin("13900000002", "123456")
sc3.like_secret(secret_id, ucode)
sc3.fetch_comments(secret_id)

r, e = sc3.post_comment(secret_id, ucode, "hello")
r, e = sc3.post_comment(secret_id, ucode, "world")
if not e:
	print r
	commentid = r['comment_id']
	comment_ucode = r['ucode']
	print "sc2 would like sc3's comment"
	r, e = sc2.like_comment(secret_id, commentid, comment_ucode)
	print "?"

timediff()
################################################
print "\nStarts fetch notices!\n"

sc1.fetch_comments(secret_id)
r, e = sc1.fetch_notice()
if not e:
	print r


timediff()
sc1.fetch_comments(secret_id)
timediff()
r, e = sc1.fetch_notice()
if not e:
	print r	
timediff()

r, e = sc2.fetch_notice()
if not e:
	print r

r, e = sc3.fetch_notice()
if not e:
	print r


sc1.signout()
sc2.signout()
sc3.signout()