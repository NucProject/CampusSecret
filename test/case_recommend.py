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
import string
from datetime import *
from secretclient import *

host = "127.0.0.1"       #local host

def check(e):
	if e:
		raise Exception


#########################################################################
sc1 = SecretClient(host)
r, e = sc1.adminsignin("yuzhongmin", "123456")    #login admin account
print r
check(e)

sc2 = SecretClient(host)
r, e = sc2.signin("13900000003", "123456")   #login user account,this is a user from Tsinghua University
print r
check(e)

sc3 = SecretClient(host)
r, e = sc3.signin("13900000012", "123456")   #login user account,this is a user from Peking University.
print r
check(e)

sc4 = SecretClient(host)
r, e = sc4.signin("13900000021", "123456")   #login user account,this is a user from renmin university.
##########################################################################
#sc2 fetch latest secret
r, e = sc2.latest_secrets()
check(e)
print r

##########################################################################
#sc3 post some secrets
c = "我可是第一个北大用户啊，哼！【%s】" % datetime.utcnow()
r, e = sc3.post_secret(c, 1)
print r
secret= r['secret']
secret_id = secret['secret_id']
check(e)

##########################################################################
#sc3 fetch latest secrets
r, e = sc3.latest_secrets()
check(e)
print r

##########################################################################
#sc1 recommend a secret,secretid is 10,come from Tsinghua University,schoolid is Peking University and Renmin University of China
r, e = sc1.recommend_secret(secret_id, "1002;1003")
check(e)
print r
schoolidstr = ''
for i in r:
	schoolid = i['school_id'] + ';'
	schoolidstr = schoolidstr + schoolid
	print(i['recommend'])
assert(schoolidstr == '1002;1003;')

##########################################################################
#sc3 fetch latest secrets again
r, e = sc3.latest_secrets()
check(e)
print r

##########################################################################
#sc2 fetch latest secret
r, e = sc2.latest_secrets()
check(e)
print r

##########################################################################
#sc1 recommend a secret,secretid is 330,come from Peking University,schoolid is Tsinghua University
r, e = sc1.recommend_secret("330", "1001")
check(e)
print r
schoolidstr = ''
for i in r:
	schoolid = i['school_id'] + ';'
	schoolidstr = schoolidstr + schoolid
	print(i['recommend'])
assert(schoolidstr == '1001;')
##########################################################################
#sc2 fetch latest secret
r, e = sc2.latest_secrets()
check(e)
print r
first = r['items'][0]['secret_id']
assert(int(first) == 330)

##########################################################################
#sc4 fetch latest secret
r, e = sc4.latest_secrets()
check(e)
print r