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

reload(sys)
sys.setdefaultencoding("utf-8")


__REGISTER_SIGNIN_CASES__ = True
__SECRET_CASES__ = True
__COMMENT_CASES__ = True
__SHARED_SECRETS_CASE__ = True
__NOTICE_CASES__ = True
__FEEDBACK_VERSIONS_CASES__ = True

###############################################################################
"""
注册/登录/修改学校相关
"""
if __REGISTER_SIGNIN_CASES__:
	# 管理员账户保证被测试的注册手机号不存在 (存在先删除)
	sca = SecretClient(None)
	sca.adminsignin('yuzhongmin', '123456')

	username = '13900001099'	# 要注册的账号 139 0000 1099

	r, e = sca.get_userid(username)
	if r.has_key('user_id'):
		# delete user by admin if found the userid in Database
		r, e = sca.delete_user(r['user_id'])
		assert(r['deleted'] == True)


	sc = SecretClient(None)
	r, e = sc.fetch_verifycode(username)
	assert(r['verify'] == 'Sent')

	# '1111' 是测试账号1390000****的唯一验证码
	r, e = sc.register(username, '123456', '1111')
	assert(r['signin'] == True)
	assert(r['register'] == 'NotCompleted')
	userid = r['user_id']	#新用户ID

	r, e = sc.finish_register(1000, 28003, 2012)
	assert(r['changed'] == True)
	assert(r['school_id'] == '1000')
	assert(r['academy_id'] == '28003')
	assert(r['grade'] == '2012')

	# 第一次更换学校信息
	r, e = sc.change_schoolinfo(1000, 28004, 2013)
	assert(r['changed'] == True)
	assert(r['school_id'] == '1000')
	assert(r['academy_id'] == '28004')
	assert(r['grade'] == '2013')

	# 第二次更换学校信息
	# 修改失败 PHP: const ChangeSchoolInfoOverTwice = 1020;
	r, e = sc.change_schoolinfo(1000, 28003, 2012)
	assert(r.has_key('errorCode') and r['errorCode'] == 1020)

	# 重新登录，信息确实没有被修改
	r, e = sc.signout()
	assert(r['signin'] == False)

	r, e = sc.signin(username, "123456")
	assert(r['school_id'] == '1000')
	assert(r['academy_id'] == '28004')
	assert(r['grade'] == '2013')

	# 管理员删除测试用户
	r, e = sca.delete_user(userid)
	userid = None
	assert(r['deleted'] == True)
	

###############################################################################
###############################################################################
"""
秘密信息流/提问/发布秘密相关
"""
if __SECRET_CASES__:
	sc1 = SecretClient(None)
	sc1.signin('13900001001', "123456")

	sc2 = SecretClient(None)
	sc2.signin('13900001002', "123456")

	r, e = sc1.latest_secrets()
	check(e)
	assert(r.has_key('items'))
	assert(r['question'].has_key('question_id'))
	sc1.print_secrets(r['items'])

	###############################
	# 1. 发一条秘秘密
	# 2. 再取应该可以取到
	# 3. 然后移除它 
	# 4. 自己看不到
	# 5. 但是sc2可以看到
	# 6. 然后删除它
	# 7. sc2看不到
	r, e = sc1.post_secret("发一条秘密，先移除，再删除。", 2)
	check(e)
	assert(r.has_key('secret'))
	assert(r['secret'].has_key('secret_owner'))
	secret_id = r['secret']['secret_id']

	r, e = sc1.latest_secrets()
	assert(contains_item(r['items'], 'secret_id', secret_id))

	sc1.remove_secret(secret_id)
	r, e = sc1.latest_secrets()
	assert(not contains_item(r['items'], 'secret_id', secret_id))

	r, e = sc2.latest_secrets()
	assert(contains_item(r['items'], 'secret_id', secret_id))

	r, e = sc1.delete_secret(secret_id)
	r, e = sc2.latest_secrets()
	assert(not contains_item(r['items'], 'secret_id', secret_id))

	###############################
	r1, e = sc1.past_secrets()
	check(e)

	r2, e = sc1.past_secrets()
	check(e)

	print min_secret_id(r1['items']), max_secret_id(r2['items'])
	assert(min_secret_id(r1['items']) > max_secret_id(r2['items']))
	


###############################################################################
###############################################################################
"""
评论相关
"""
if __COMMENT_CASES__:
	sc1 = SecretClient(None)
	sc1.signin("13900001001", "123456")
	sc2 = SecretClient(None)
	sc2.signin("13900001002", "123456")
	sc3 = SecretClient(None)
	sc3.signin("13900001003", "123456")
	sc4 = SecretClient(None)
	sc4.signin("13900001004", "123456")
	sc5 = SecretClient(None)
	sc5.signin("13900001005", "123456")
	sc6 = SecretClient(None)
	sc6.signin("13900001006", "123456")
	sc7 = SecretClient(None)
	sc7.signin("13900001007", "123456")
	sc8 = SecretClient(None)
	sc8.signin("13900001008", "123456")
	sc9 = SecretClient(None)
	sc9.signin("13900001009", "123456")
	sc0 = SecretClient(None)		# Secret owner (发秘密的人)
	sc0.signin("13900001010", "123456")

	r, e = sc0.post_secret('测试评论开始了，你喜欢吴秀波吗？', 2)
	check(e)
	secretid = r['secret']['secret_id']

	r, e = sc1.post_comment(secretid, "1-说个段子...")
	check(e)

	comment1 = r['items'][0]
	comment1_id = comment1['comment_id']
	assert(comment1.has_key('mine'))
	print "comment 1 id = " + str(comment1_id)

	r, e = sc2.post_comment(secretid, "2-我不喜欢吴秀波!")
	check(e)
	assert(len(r['items']) == 2)
	comment2 = r['items'][1]
	comment2_id = comment2['comment_id']
	assert(comment2.has_key('mine'))
	
	# 1楼和2楼的头像不可能是一样的
	assert(comment1['avatar'] != comment2['avatar'])

	r, e = sc3.post_comment(secretid, "3-我猜2楼会被删掉")
	check(e)
	assert(len(r['items']) == 3)
	comment3 = r['items'][2]
	comment3_id = comment3['comment_id']
	# 1楼和2楼和3楼的头像不可能是一样的
	assert(comment1['avatar'] != comment3['avatar'])
	assert(comment2['avatar'] != comment3['avatar'])


	r, e = sc1.post_comment(secretid, "4-必然会被删掉，等楼主删帖")
	check(e)
	assert(len(r['items']) == 4)
	comment4 = r['items'][3]
	comment4_id = comment4['comment_id']
	# 1楼和2楼和3楼的头像不可能是一样的
	assert(comment2['avatar'] != comment4['avatar'])
	assert(comment3['avatar'] != comment4['avatar'])
	assert(comment1['avatar'] == comment4['avatar'])

	# 楼主删了2楼 (让你不喜欢吴秀波)
	r, e = sc0.delete_comment(secretid, comment2_id)
	assert(r['deleted'] == True)
	assert(r['comment_id'] == comment2_id)

	r, e = sc0.post_comment(secretid, '5-我把2楼给删除了')
	check(e)
	comment5 = r['items'][3]
	assert(len(r['items']) == 4)	# 如果不是删除了2楼，这里应该是5的
	assert(comment5['floor'] == 5)	# 还是5楼，而不是4楼
	assert(comment5['secret_owner'] == True)
	assert(not comment1.has_key('secret_owner'))

	#########################################################
	avatar = []
	for i in range(6, 30):
		r, e = sc2.post_comment(secretid, '%s-让你丫删我评论' % i)
		comment = r['items'][-1:][0]
		avatar.append(comment['avatar'])
	assert(len(set(avatar)) == 1)
	#########################################################
	r, e = sc4.post_comment(secretid, '?-看看刷屏')
	check(e)
	avatar = []
	for comment in r['items']:
		avatar.append(comment['avatar'])
	# 只有5个人发过秘密，所以只会出现5种头像
	assert(len(set(avatar)) == 5)

	#########################################################
	# 赞一楼的评论
	sc5.like_comment(secretid, comment1_id, 1)

	r, e = sc5.fetch_comments(secretid)
	assert(len(r['items']) == 29)
	assert(r['items'][0]['iliked'] == 1)
	for i in range(1, 20):
		assert(not r['items'][i].has_key('mine'))	# sc5没有任何评论
		assert(not r['items'][i].has_key('iliked'))	# sc5赞的仅仅是一楼

	#########################################################
	r, e = sc6.fetch_comments(secretid)
	assert(r['items'][0]['liked_count'] == 1)
	for i in range(1, 20):
		assert(r['items'][i]['liked_count'] == 0)	#仅仅是一楼	被赞

	#########################################################
	# sc6 赞过后, 评论1的liked_count 变为2
	sc6.like_comment(secretid, comment1_id, 1)
	r, e = sc6.fetch_comments(secretid)
	assert(r['items'][0]['liked_count'] == 2)
	for i in range(1, 20):
		assert(r['items'][i]['liked_count'] == 0)	#仅仅是一楼	被赞	

###############################################################################
###############################################################################	

"""
Shared
"""
if __SHARED_SECRETS_CASE__:
	sc = SecretClient(None)
	# 进入school_id=1000的学校
	# 取到所有的secrets
	schooldid = 1001
	r, e = sc.shared_latest(schooldid)
	print r
	min_secretid = min_secret_id(r['items'])
	print "Fetch before " + str(min_secretid)

	# 取一个学校的信息流，直到没有可取的
	while True:
		r, e = sc.shared_past(schooldid, min_secretid)
		print r
		if len(r['items']) == 0:
			break
		min_secretid = min_secret_id(r['items'])



###############################################################################
###############################################################################

"""
通知
"""
if __NOTICE_CASES__:
	sc1 = SecretClient(None)
	sc1.signin("13900001001", "123456")
	sc1.disable_output()
	sc1.clear_notice()
	sc1.disable_output(False)
	
	sc2 = SecretClient(None)
	sc2.signin("13900001002", "123456")
	sc2.disable_output()
	sc2.clear_notice()
	sc2.disable_output(False)

	sc3 = SecretClient(None)
	sc3.signin("13900001003", "123456")
	sc3.disable_output()
	sc3.clear_notice()
	sc3.disable_output(False)
	
	r, e = sc1.query_notice()
	print r
	assert(r['new_comments'] == 0)
	assert(r['new_liked'] == 0)

	r, e = sc1.fetch_notice()
	assert(len(r['items']) == 0)

	###################################################################
	# 发一个秘密 (测试累加)
	r, e = sc1.post_secret('我长得很漂亮，为什么没有男朋友？', 3)
	check(e)
	secretid = r['secret']['secret_id']

	r, e = sc2.add_comment(secretid, "因为你是男生？")
	check(e)
	comment1_id = r['items'][-1:][0]['comment_id']
	print comment1_id

	###############################################################################
	# OK, Client 2 wants to make a comment at floor 2
	r, e = sc2.add_comment(secretid, "你出柜了？")
	check(e)
	comment2_id = r['items'][-1:][0]['comment_id']
	print comment2_id

	r, e = sc1.query_notice()
	assert(r['new_comments'] == 2)	# 2条评论，没有赞
	assert(r['new_liked'] == 0)     # 没有赞

	r, e = sc2.like_secret(secretid)
	check(e)
	r, e = sc3.like_secret(secretid)
	check(e)
	
	r, e = sc1.query_notice()
	assert(r['new_comments'] == 2) # 2条评论
	assert(r['new_liked'] == 1)    # sc2和sc3都赞了同一个秘密，+1

	###################################################################
	# 再发一个秘密 (测试累加)
	r, e = sc1.post_secret('姐需要一个男朋友，求包养！', 4)
	secret2_id = r['secret']['secret_id']
	
	r, e = sc3.like_secret(secret2_id)
	check(e)

	r, e = sc1.query_notice()
	assert(r['new_comments'] == 2) # 2条评论
	assert(r['new_liked'] == 2)    # sc3都赞了新秘密，再+1

	r, e = sc2.add_comment(secret2_id, "现在的女生真没节操！")
	check(e)
	r, e = sc2.add_comment(secret2_id, "算了，我包养你算了，不忍心看你堕落啊")
	check(e)
	r, e = sc2.add_comment(secret2_id, "一个月500行吗？")
	check(e)
	r, e = sc2.add_comment(secret2_id, "哦，不提供食宿。。")
	check(e)	

	r, e = sc1.query_notice()
	assert(r['new_comments'] == 6)  #评论数 2 + 4
	assert(r['new_liked'] == 2)     #赞不变，还是2

	###################################################################
	# 再发一个秘密 (测试累加)
	r, e = sc1.post_secret('无聊的男生滚开，姐说真的！上天赐给我一个BF吧！', 4)
	check(e)
	secret3_id = r['secret']['secret_id']

	r, e = sc2.add_comment(secret3_id, "我也说真的，考虑我吧！")
	check(e)
	r, e = sc3.add_comment(secret3_id, "1楼滚开，楼主的未来属于我")
	check(e)

	r, e = sc1.query_notice()
	assert(r['new_comments'] == 8)  #评论数 2 + 4 + 2
	assert(r['new_liked'] == 2)     #赞不变，还是2	

	###################################################################
	# 再发一个秘密 (测试累加)
	r, e = sc1.fetch_comments(secret2_id)	# sc1看过了有四条评论的秘密，这样数量都发生了变化

	r, e = sc1.query_notice()
	assert(r['new_comments'] == 4)  #评论数 2 + 4 + 2 - 4
	assert(r['new_liked'] == 1)     #赞-1 = 1

	r, e = sc1.fetch_notice()
	assert(len(r['items']) == 2)	#有两个秘密相关的提醒 另外有一个（secret2_id）看过了
	assert(r['items'][0]['others_unread'] == 2)
	assert(r['items'][1]['others_unread'] == 2)

	###################################################################
	r, e = sc2.add_comment(secret2_id, "锲而不舍的追求~希望你收到我的提醒")
	check(e)
	r, e = sc1.query_notice()
	assert(r['new_comments'] == 5)  #评论数 2 + 4 + 2 - 4 + 1
	assert(r['new_liked'] == 1)     #赞-1 = 1

	r, e = sc1.fetch_notice()
	assert(len(r['items']) == 3)	#有3个秘密相关的提醒
	assert(r['items'][0]['others_unread'] + r['items'][1]['others_unread'] + r['items'][2]['others_unread'] == 5)
	
	###################################################################
	# sc2的提醒情况
	sc1.add_comment(secret2_id, '有多远滚多远！别逼姐删帖子!')

	r, e = sc2.query_notice()
	check(e)
	assert(r['new_comments'] == 2)

	r, e = sc2.fetch_notice()
	assert(len(r['items']) == 2)	#sc2有2个秘密相关的提醒(sc1在secret2中和sc2在secret3中产生的)
	###################################################################
	# 删除一个秘密测试
	r, e = sc1.delete_secret(secret3_id)
	check(e)

	r, e = sc1.query_notice()
	assert(r['new_comments'] == 2)
	assert(r['new_liked'] == 1)     #赞-1 = 1

	r, e = sc1.fetch_notice()
	assert(len(r['items']) == 1)	#有1个秘密相关的提醒(删了一个 看了一个 就剩下一个了)
	assert(r['items'][0]['others_unread'] == 2)


"""
意见反馈，版本控制
"""
if __FEEDBACK_VERSIONS_CASES__:
	#版本控制
	sca = SecretClient(None)
	sca.adminsignin('yuzhongmin', '123456')
	r, e = sca.version('1.0.2')		#查v1.0.2的版本信息
	if (r['version']==None):		#如果不存在v1.0.2,则创建v2.0.0
		version = '2.0.0'
		policy = "1:3.0.0:xxx"
		r, e = sca.create_version(version, policy) 
		assert(r['set'] == True)	#判断是否创建成功
	r, e = sca.version('2.0.0')		#再次检验刚才创建的新版本是否正确
	assert(r['version'] == '3.0.0')

	#举报秘密
	#1.先创建两个用户
	#2.用户1发一条秘密
	#3.用户2取最新的秘密
	#4.用户2举报用户1刚刚发的秘密
	sc1 = SecretClient(None)
	sc1.signin('13900001001', "123456")
	sc2 = SecretClient(None)
	sc2.signin('13900001002', "123456")
	r, e = sc1.post_secret("发一条要被用户2举报的秘密。", 2)
	secret_id = r['secret']['secret_id']
	r, e = sc2.latest_secrets()
	if(contains_item(r['items'], 'secret_id', secret_id) == True):	#如果用户2取最新的信息流中有用户1发的秘密则举报
		reason = 1	
		r, e = sc2.report_secret(secret_id, reason)
		assert(r['report'] == True)

    #举报评论
    #1.用户1发一条秘密
    #2.用户2对用户1秘密发评论
    #3.用户1举报用户2的评论
	r, e = sc1.post_secret("用户2敢来评论，我就举报", 2)
	secret_id = r['secret']['secret_id']
	r, e = sc2.post_comment(secret_id, "有种就举报")
	comment = r['items'][0]
	comment_id = comment['comment_id']
	reason = 2
	r, e = sc1.report_comment(secret_id, comment_id, reason)
	assert(r['reported'] == True)


"""
管理员API相关
"""


