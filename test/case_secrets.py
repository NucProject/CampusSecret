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

sc1 = SecretClient()
r, e = sc1.signin("13900001001", "123456")
print r
check(e)

sc2 = SecretClient()
r, e = sc2.signin("13900001002", "123456")
check(e)

########################################################################
# fetch latest secrets
# TODO:
r, e = sc1.latest_secrets()
print r
check(e)

print r
print r['question']

#exit()
########################################################################
# pick a random question
# TODO:
r, e = sc1.pick_question()
check(e)
print r

########################################################################
# post secrets and check the results
c = "[TEST] FFF【%s】" % datetime.utcnow()
r, e = sc1.post_secret(c, 1)
print r
check(e)
secret = r['secret']
print r
print secret['background']
assert(int(secret['background']) == 1)
assert(int(secret['comment_count']) == 0)
assert(int(secret['liked_count']) == 0)
assert(len(secret['academy'].encode("utf8")) > 0)
assert(secret['grade'] == sc1.grade)
assert(secret['school_id'] == sc1.schoolid) 
assert(secret['academy_id'] == sc1.academyid)


secret_id = secret['secret_id']
print "hey in there!!!!"
print secret_id
print "hey in there!!!!"
########################################################################
# Client 2 like the secret
r, e = sc2.like_secret(secret_id)
print r
check(e)
assert(r['liked'] == True)


########################################################################
# fetch latest secrets check liked count (should be 1)
r, e = sc1.latest_secrets()
check(e)
print r
secret0 = r['items'][0]
assert(not secret0.has_key('iliked'))
assert(secret0['secret_id'] == secret_id)
assert(secret0['liked_count'] == 1)

########################################################################
# Client 2 cancel like the secret
r, e = sc2.like_secret(secret_id, False)
check(e)
assert(r['liked'] == False)


########################################################################
# fetch latest secrets check liked count (should be back to 0 )
r, e = sc1.latest_secrets()
check(e)
# top secret
secret0 = r['items'][0]
assert(not secret0.has_key('iliked'))
assert(secret0['secret_id'] == secret_id)
assert(secret0['liked_count'] == 0)		#

########################################################################
# Client 1 (Secret owner) like the secret
r, e = sc1.like_secret(secret_id)
check(e)
assert(r['liked'] == True)

########################################################################
# fetch latest secrets check liked count (should be back to 1 for owner liked )
r, e = sc1.latest_secrets()
check(e)
# top secret
secret0 = r['items'][0]
assert(secret0['iliked'] == 1)
assert(secret0['secret_id'] == secret_id)
assert(secret0['liked_count'] == 1)

########################################################################
# Client 2 fetch latest secrets check liked count (should be 1 for owner liked, but NOT iliked)
r, e = sc2.latest_secrets()
check(e)
# top secret
secret0 = r['items'][0]
assert(not secret0.has_key('iliked'))
print "----" * 40
print secret0['secret_id'], secret_id
assert(secret0['secret_id'] == secret_id)
assert(secret0['liked_count'] == 1)

########################################################################
# Client 1 (The secret owner) cancel like the secret
r, e = sc1.like_secret(secret_id, False)
check(e)
assert(r['liked'] == False)


########################################################################
# fetch latest secrets check liked count (should be back to 0 for owner canceled liked)
r, e = sc1.latest_secrets()
check(e)
# top secret
secret0 = r['items'][0]
assert(not secret0.has_key('iliked'))
assert(secret0['secret_id'] == secret_id)
assert(secret0['liked_count'] == 0)


########################################################################
# Client 2 remove the secret, so he can NOT see it anymore
r, e = sc2.remove_secret(secret_id)
check(e)
assert(r['removed'] == True)

########################################################################
# Can NOT see the secret anymore, so the id would NOT exists in the stream
r, e = sc2.latest_secrets()
check(e)
# top secret
for s in r['items']:
	assert(s['secret_id'] != secret_id)


########################################################################
# BUT client 1 can see it (Remove is NOT delete)
r, e = sc1.latest_secrets()
check(e)
secret0 = r['items'][0]
assert(secret0['secret_id'] == secret_id)


########################################################################
# Client 2 try to DELETE the secret, but he would failed.
r, e = sc2.delete_secret(secret_id)
print r, e
assert(e != None)
assert(r['errorCode'] == 2002)
print "I can NOT delete it"
########################################################################
# Client 1 try to DELETE the secret, he can delete.
r, e = sc1.delete_secret(secret_id)
print r
check(e)

assert(r['deleted'] == True)


########################################################################
# client 1 can NOT see it (Deleted)
r, e = sc1.latest_secrets()
check(e)
print "--"*300
print r
sc1_min_secret_id = 999999
for s in r['items']:
	print int(s['secret_id'])
	sc1_min_secret_id = min(sc1_min_secret_id, int(s['secret_id']))
	assert(s['secret_id'] != secret_id)

print sc1_min_secret_id, sc1.min_secretid
#assert(min_secret_id == sc1.min_secretid)

########################################################################
# client 2 can NOT see it (Deleted)
r, e = sc2.latest_secrets()
check(e)
print "--"*300
print r
sc2_min_secret_id = 999999
for s in r['items']:
	print int(s['secret_id'])
	sc2_min_secret_id = min(sc2_min_secret_id, int(s['secret_id']))
	assert(s['secret_id'] != secret_id)

print sc2_min_secret_id, sc2.min_secretid
#assert(min_secret_id == sc1.min_secretid)

########################################################################
# client 1 fetch PAST secrets
r, e = sc1.past_secrets()
past_secrets = r['items']
for s in past_secrets:
	assert(int(s['secret_id']) < int(sc1_min_secret_id))

########################################################################
# post secrets and check the results (change to another secret)
c = "[TEST] 被举报的秘密！【%s】" % datetime.utcnow()
r, e = sc2.post_secret(c, 3)
check(e)
secret = r['secret']
secret_id = secret['secret_id']


########################################################################
# client 1 fetch latest secrets to report
r, e = sc1.latest_secrets()
check(e)
secret0 = r['items'][0]
assert(secret0['secret_id'] == secret_id)

########################################################################
# client 1 fetch latest secrets to report
r, e = sc1.report_secret(secret_id, 1)
check(e)
assert(r['report'] == True)
assert(r['secret_id'] == secret_id)

########################################################################
# client 1 can NOT see it (Report includes Remove)
r, e = sc1.latest_secrets()
check(e)
for s in r['items']:
	assert(s['secret_id'] != secret_id)

########################################################################
# Client 2 fetch latest secrets and He can see it
r, e = sc2.latest_secrets()
check(e)
# top secret
secret0 = r['items'][0]
print secret0['secret_id'] , secret_id, "21312321321321321321321"
assert(secret0['secret_id'] == secret_id)


#########################################################################
# Image about (Client 2 post a secret with Image. then sc1 can download it)
print '-' * 40
r, e = sc2.get_uptoken()
check(e)
uptoken = r['uptoken']
imagekey = r['key']
sc2.upload_image(uptoken, imagekey, "test_image.jpg")
r, e = sc2.post_secret("喜欢我的大黄蜂吗?", 0, imagekey)
check(e)
secret_with_image = r['secret']
assert(secret_with_image.has_key('background_image'))
sc1.download_image(secret_with_image['background_image'], imagekey, True)

print '-' * 40

sc1.signout()
sc2.signout()


