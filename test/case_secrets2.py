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

def check_secret_entry(s, show = False):
	# background
	assert(s.has_key("background"))
	if s.has_key("background_image") and s['background_image'] != None:
		assert("%s" % s['background'] == "0")
	# counts
	assert(s.has_key("liked_count"))
	assert(s.has_key("comment_count"))
	if (show):
		pass

sc = SecretClient(host)
r, e = sc.signin("12345678900", "123456")


sc.min_secretid = 30
# fetch past secrets
r, e = sc.past_secrets()
if not e:
	items = r['items']
	count_secrets = len(items) 
	print "COUNT: ", count_secrets, "\t\tMIN: ", sc.min_secretid
	assert(count_secrets <= 11)
	for i in items:
		print i
		check_secret_entry(i)
