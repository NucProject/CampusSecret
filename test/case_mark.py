#!/usr/bin/env python
# -*- coding: utf-8 -*-
# Test mark in v1.1

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

##############################################################
sc0 = SecretClient(None)
r, e = sc0.signin("13900001001", "123456")

sc1 = SecretClient(None)
r, e = sc1.signin("13900001002", "123456")

sc2 = SecretClient(None)
r, e = sc2.signin("13900001003", "123456")

#sc0 发送秘密, sc1和sc2来mark
r, e = sc0.post_secret("发秘密，等你来mark~", 10)
check(e)

secret_id = r['secret']['secret_id']

# sc1 清除其他的提醒，然后关注新的评论再取消关注，这样deleted里面应该含有这个secret id
sc1.clear_notice()
r, e = sc1.mark_secret(secret_id)
check(e)

r, e = sc1.unmark_secret(secret_id)
check(e)

r, e = sc1.fetch_notice()
check(e)
assert(secret_id in r['deleted'])

# sc1 清除其他的提醒，然后关注新的评论
# Remark!
r, e = sc1.mark_secret(secret_id)
check(e)

r, e = sc2.add_comment(secret_id, "评论你")
check(e)
sc2.clear_notice()

# 因为sc2的评价，于是new_comments == 1
r, e = sc1.query_notice()
assert(r['new_comments'] == 1)


r, e = sc0.add_comment(secret_id, "2-评论你")

# 因为sc2的评价，于是new_comments == 1
r, e = sc1.fetch_notice()
assert(r['items'][0]['others_unread'] == 2)

# 因为sc2的评价，于是new_comments == 1
r, e = sc2.fetch_notice()
assert(r['items'][0]['others_unread'] == 1)


r, e = sc1.unmark_secret(secret_id)
r, e = sc1.fetch_notice()
check(e)
assert(len(r['items']) == 0)

r, e = sc2.unmark_secret(secret_id)
r, e = sc2.fetch_notice()
check(e)
assert(len(r['items']) == 0)