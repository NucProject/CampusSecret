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
def check(e):
	if e:
		raise Exception

def check_secret_entry(s, show = False):
	# background
	if s.has_key("background_image") and s['background_image'] != None:
		pass
	# counts
	assert(s.has_key("liked_count"))
	assert(s.has_key("comment_count"))
	if (show):
		pass

sc1 = SecretClient(host)
r, e = sc1.signin("12345678900", "123456")
check(e)

sc2 = SecretClient(host)
r, e = sc2.signin("13900000001", "123456")
check(e)


########################################################################
# post secrets and check the results
c = "[TEST] 测试秘密！【%s】" % datetime.utcnow()
r, e = sc1.post_secret(c, 1)
check(e)
secret = r['secret']
#print r
assert(int(secret['background']) == 1)
assert(int(secret['comment_count']) == 0)
assert(int(secret['liked_count']) == 0)
assert(len(secret['academy'].encode("utf8")) > 0)
assert(secret['grade'] == sc1.grade)
assert(secret['school_id'] == sc1.schoolid) 
assert(secret['academy_id'] == sc1.academyid)


sc1.signout()
sc2.signout()


