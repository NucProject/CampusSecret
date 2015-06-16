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
import time
from secretclient import *


sc1 = SecretClient()
sc1.signin("13900001001", "123456")

sc2 = SecretClient()
sc2.signin("13900001002", "123456")

sc3 = SecretClient()
sc3.signin("13900001003", "123456")

sc4 = SecretClient()
sc4.signin("13900001004", "123456")

sc5 = SecretClient()
sc5.signin("13900001005", "123456")

sc6 = SecretClient()
sc6.signin("13900001006", "123456")

sc7 = SecretClient()
sc7.signin("13900001007", "123456")

sc8 = SecretClient()
sc8.signin("13900001008", "123456")

sc9 = SecretClient()
sc9.signin("13900001009", "123456")

sc0 = SecretClient()		# Secret owner
sc0.signin("13900001010", "123456")

################################################################################
# Pub a secret for testing
c1 = "[TEST] 测试评论! 发帖人是 13900000010"
r, e = sc0.latest_secrets()

r, e = sc0.post_secret(c1, 2)
#print r
#print e
check(e)
secret = r['secret']
secretid = secret['secret_id']
################################################################################
# Fetch comments should be empty
print "begin to test comments with secretid = [", secretid, "]"

r, e = sc1.fetch_comments(secretid)
check(e)
assert(len(r['items']) == 0)


################################################################################
# So Client 1 starts to add comment
r, e = sc1.post_comment(secretid, "A-楼主有病？")
check(e)
print r 

assert(len(r['items']) == 1)
comment1 = r['items'][0]
comment1_id = comment1['comment_id']
print "@Floor 1", comment1_id

################################################################################
# So Client 2 - 5 starts to add comment
r, e = sc2.post_comment(secretid, "B-你造吗？")
check(e)
assert(len(r['items']) == 2)
comment2 = r['items'][1]
comment2_id = comment2['comment_id']
print "@Floor 2", comment2_id

################################################################################
r, e = sc3.post_comment(secretid, "C-~~~")
check(e)
assert(len(r['items']) == 3)
################################################################################
r, e = sc4.post_comment(secretid, "D-：）")
check(e)
assert(len(r['items']) == 4)
comment4 = r['items'][3]
comment4_id = comment4['comment_id']
print "@Floor 2", comment4_id
################################################################################
r, e = sc5.post_comment(secretid, "E-服了")
check(e)
assert(len(r['items']) == 5)
################################################################################
r, e = sc6.post_comment(secretid, "F-3楼，你无聊不？")
check(e)
assert(len(r['items']) == 6)

exit()

################################################################################
# So Client 7 starts to fetch the comments
r, e = sc7.fetch_comments(secretid)
check(e)
sequence = ""
avatars = []
s = set()
for i in r['items']:
	sequence += i['content'][0:1]
	avatars.append(i['avatar'])
assert(sequence == 'ABCDEF')
assert(len(set(avatars)) == 6)

################################################################################
# So Client 6 add 2 comments, floor == 7, BUT avatar should has 6 type
r, e = sc6.post_comment(secretid, "G-我是6楼和7楼? 头像一样吧?...")
check(e)
avatars = []
floors = []
for i in r['items']:
	avatars.append(i['avatar'])
	floors.append(i['floor'])
assert(len(avatars) == 7)
assert(len(set(avatars)) == 6)
assert(len(r['items']) == 7)
assert("".join(map(str, floors)) == "1234567")

################################################################################
# So Client 0 (Secret owner) 
r, e = sc0.post_comment(secretid, "H-我是楼主")
check(e)



################################################################################
# So Client 7, 8, 9 starts like the floor 2 and floor 4
r, e = sc7.like_comment(secretid, comment2_id, 2)
print r
check(e, r)

r, e = sc8.like_comment(secretid, comment2_id, 2)
check(e)

r, e = sc8.like_comment(secretid, comment4_id, 4)
check(e)

r, e = sc9.like_comment(secretid, comment2_id, 2)
check(e)

r, e = sc9.like_comment(secretid, comment2_id, 2, False)
print r
check(e)

################################################################################
# So Client 7 starts to fetch the comments
r, e = sc7.fetch_comments(secretid)
print r
check(e)
for c in r['items']:
		
	if c['floor'] == 2:
		assert(c['liked_count'] == 2)
		assert(c['iliked'] == 1)
	if c['floor'] == 4:
		assert(c['liked_count'] == 1)

################################################################################
# So Client 2 fetch the comments, and found the second comment is send by Client 2 itself.
r, e = sc2.fetch_comments(secretid)
check(e)
for c in r['items']:
	assert(not c.has_key('iliked'))
	if c['floor'] == 2:
		assert(c['liked_count'] == 2)
	if c['floor'] == 4:
		assert(c['liked_count'] == 1)

################################################################################
# So Client 8 starts to fetch the comments
r, e = sc8.fetch_comments(secretid)
check(e)
for c in r['items']:
		
	if c['floor'] == 2 or c['floor'] == 4:
		assert(c['iliked'] == 1)

################################################################################
# So Client 7 starts to fetch the comments
r, e = sc7.fetch_comments(secretid)
check(e)
for c in r['items']:
		
	if c['floor'] == 2:
		assert(c['iliked'] == 1)

################################################################################
# Client 2 try to delete comment Client 1's comment would failed
r, e = sc2.delete_comment(secretid, comment1_id)
assert(r == 3002)
print "Client 2 is NOT able to delete comment 1"

################################################################################
# Client 1 can remove his comment
r, e = sc1.delete_comment(secretid, comment1_id)
print r
check(e)


################################################################################
# So Client 2 fetch the comments, the floor 1 should NOT here
r, e = sc2.fetch_comments(secretid)
check(e)
for c in r['items']:
	assert(c['floor'] != 1)

################################################################################
# So Client 1 fetch the comments, the floor 1 should NOT here also
r, e = sc1.fetch_comments(secretid)
check(e)
for c in r['items']:
	assert(c['floor'] != 1)

################################################################################
# Client 0 (Secret Owner) fetch comments, he can see the field secret_owner
r, e = sc0.fetch_comments(secretid)
check(e)
print r
secret_b = r['items'][-1:][0]
assert(secret_b['floor'] == 8)
assert(secret_b.has_key('secret_owner') and secret_b['secret_owner'] == 1)

################################################################################
# Client 0 (Secret Owner) delete some comment, he can see the field secret_owner
r, e = sc0.delete_comment(secretid, comment2_id)
print r
check(e)

################################################################################
# So Client 1 fetch the comments, the floor 1 and 2 should NOT here also
r, e = sc1.fetch_comments(secretid)
check(e)
for c in r['items']:
	f = c['floor']
	assert(f != 1 and f != 2)


################################################################################
# For count
sc1.post_comment(secretid, "More, 接着说啊1")
sc2.post_comment(secretid, "PHP和JavaScript是世界上最烂的两个语言，但是又用得最多。")
sc3.post_comment(secretid, "MLGBD")
sc4.post_comment(secretid, "世界三大毒瘤")
sc6.post_comment(secretid, "寻找二师兄")
sc7.post_comment(secretid, "Hello kkkkkk")
sc2.post_comment(secretid, "PHP好烂")
sc9.post_comment(secretid, "Python不错")
sc0.post_comment(secretid, "C++好，可惜用不上")
sc2.post_comment(secretid, "说点啥？Java好，内存买不起")
sc3.post_comment(secretid, "Python的tornado可以试一试")
sc3.post_comment(secretid, "我是多少楼")
sc4.post_comment(secretid, "可以在这里征婚吗？")
sc5.post_comment(secretid, "Phalcon是我见过最好的PHP框架了。")
sc7.post_comment(secretid, "Web后台开发都是一回事儿啊。。。")
sc6.post_comment(secretid, "Android和iOS都是前端的")
sc7.post_comment(secretid, "HTML5很强？")
sc8.post_comment(secretid, "为什么你不说CSS好玩呢？好玩？。。。 。。。")
sc9.post_comment(secretid, "呵呵，疯了啊？")
sc0.post_comment(secretid, "竟无语凝噎...")
sc1.post_comment(secretid, "More, 接着说啊")


sc1.signout()
sc2.signout()
sc3.signout()
sc4.signout()
sc5.signout()
sc6.signout()
sc7.signout()
sc8.signout()
sc9.signout()
sc0.signout()
