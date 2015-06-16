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
import time  

########################################################################
"""
comments notice count
ignore like count in this case
"""


def check(e, r = None):
	if e:
		if r:
			print "Error =", r
		raise NameError

def print_notice(n):
	for i in n['items']:
		print i

def clear_notice(sc, r):
	print "#" * 80
	print "CLEAR"
	print r
	for c in r['items']:
		sc.fetch_comments(c['secret_id'])
	print "#" * 80

def should_has_notice(r, secretid, num):
	if num > 0:
		assert(len(r['items']) > 0)
	for c in r['items']:
		if c['secret_id'] == secretid and c['others_unread'] != num:
			print c
			print "secret %s should have %s notices, but in fact get %s." % (secretid, num, c['others_unread'])
			assert(False)

def should_has_notice_liked(r, secretid):
	assert(len(r['items']) > 0)
	for c in r['items']:
		if c['secret_id'] == secretid:
			assert(c.has_key('others_liked'))

def should_not_has_notice_liked(r, secretid):
	for c in r['items']:
		if c['secret_id'] == secretid:
			assert(not c.has_key('others_liked'))


sc1 = SecretClient(None)
#sc1 = SecretClient()
r, e = sc1.signin("13900001001", "123456")
print r

r, e = sc1.query_notice()
print r

r, e = sc1.fetch_notice()
print r



exit()


if r.has_key('items') and len(r['items']) > 0:
	clear_notice(sc1, r)


sc2 = SecretClient(host)
sc2.signin("13900000002", "123456")



r, e = sc2.fetch_notice()
print r



if len(r['items']) > 0:
	clear_notice(sc2, r)

sc3 = SecretClient(host)
sc3.signin("13900000003", "123456")

r, e = sc3.fetch_notice()
if len(r['items']) > 0:
	clear_notice(sc3, r)

sc4 = SecretClient(host)
sc4.signin("13900000004", "123456")

r, e = sc4.fetch_notice()
if len(r['items']) > 0:
	clear_notice(sc4, r)
###############################################################################
r, e = sc1.latest_secrets()
check(e)
r, e = sc1.post_secret("很久没有去电影院看过电影了~", 8)
check(e)

secret_id = r['secret']['secret_id']
print "<SECRET: [", secret_id, "]>"
print "#" * 80
###############################################################################
###############################################################################
# fectch notices for Clear
r, e = sc1.fetch_notice()
check(e)
if len(r['items']) > 0:
	# visit the secret details to clear the notices
	clear_notice(sc1, r)

# # check notices is Clear?
r, e = sc1.fetch_notice()
check(e)
print r

#exit()
assert(len(r['items']) == 0)

print "#" * 80
print "#" * 80
###############################################################################
###############################################################################
#
r, e = sc2.add_comment(secret_id, "天下无贼")
print r
check(e)
comment1_id = r['items'][-1:][0]['comment_id']
print comment1_id

###############################################################################
# OK, Client 2 wants to make a comment at floor 2
r, e = sc2.add_comment(secret_id, "色戒")
check(e)
comment2_id = r['items'][-1:][0]['comment_id']
print comment2_id


r, e = sc3.like_comment(secret_id, comment1_id, 1)
check(e)
r, e = sc4.like_comment(secret_id, comment1_id, 1)
check(e)


r, e = sc4.like_secret(secret_id)
check(e)

r, e = sc1.query_notice()
assert(r['new_comments'] == 2)
assert(r['new_liked'] == 1)

###############################################################################
# because Client 1 & 2 make comments, so Secret owner can fetch 2 notice!
r, e = sc1.fetch_notice()
print r
check(e)
print_notice(r)
should_has_notice(r, secret_id, 2)
should_has_notice_liked(r, secret_id)

###############################################################################
# because Client 1 & 2 make comments, so Secret owner can fetch 2 notice! 
# (Did not visit the secret, so the status would NOT be clear)
r, e = sc1.fetch_notice()
check(e)
print_notice(r)
should_has_notice(r, secret_id, 2)
should_has_notice_liked(r, secret_id)

# visit to clear the status
sc1.fetch_comments(secret_id)

###############################################################################
# (Did not visit the secret, so the status would NOT be clear)
r, e = sc1.fetch_notice()
check(e)
print_notice(r)
should_has_notice(r, secret_id, 0)


###############################################################################
# because Client 3 and 4 like comments sent by sc2 , so sc2 can fetch 1 notice about like!
r, e = sc2.fetch_notice()
check(e)
print_notice(r)
should_has_notice_liked(r, secret_id)

r, e = sc2.fetch_comments(secret_id)
check(e)

###############################################################################
# because Client 3 and 4 like comments sent by sc2 , but fetch the details, So sc2 can NOT fetch 1 notice about like!
r, e = sc2.fetch_notice()
check(e)
print_notice(r)
should_not_has_notice_liked(r, secret_id)




sc1.signout()
sc2.signout()
sc3.signout()
sc4.signout()