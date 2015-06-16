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

username = '13900009021'

sca = SecretClient()
sca.adminsignin('yuzhongmin', '123456')

r, e = sca.version('1.0.0')
print r


r, e = sca.get_userid(username)
print r
if (type(r) != int and r != 4):
	r, e = sca.delete_user(r['user_id'])
	print r



sc = SecretClient()
r, e = sc.fetch_verifycode(username)
print r



r, e = sc.register(username, '123456', '1111')
print r
assert(r['signin'] == True)
assert(r['register'] == 'NotCompleted')
userid = r['user_id']

r, e = sc.finish_register(1003, 2073, 2012)
print r
assert(r['school_id'] == '1003')
assert(r['academy_id'] == '2073')
assert(r['grade'] == '2012')


r, e = sc.change_schoolinfo(1001, 1906, 2009)
print r

assert(r['school_id'] == '1001')
assert(r['academy_id'] == '1906')
assert(r['grade'] == '2009')


# change school info twice error.
r, e = sc.change_schoolinfo(1001, 1905, 2010)
print r
assert(r == 1020)


#################################################
r, e = sc.delete_user(userid)
assert(r == 201)

# Admin Only admin can delete a user.
sca = SecretClient()
sca.adminsignin('yuzhongmin', '123456')
r, e = sca.delete_user(userid)
assert(r['deleted'] == True)

sc.signout()
sca.signout()
