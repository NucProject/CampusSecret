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
import qiniu.io


user_agent = 'Mozilla/5.0 (Linux; U; Android 2.3.7; en-us; Nexus One Build/FRF91) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1'

host1 = "127.0.0.1"
host2 = "182.92.80.195"
console = False



cookie_support = urllib2.HTTPCookieProcessor(cookielib.CookieJar())
opener = urllib2.build_opener(cookie_support, urllib2.HTTPHandler)
opener.addheaders = [("User-agent", user_agent), ("Accept", "*/*")]


def pp(*c):
	if console:
		print c
	else: 
		for i in c:
			print i and i.encode("utf8"),
		print

def print_secret(secret):
	if secret.has_key("background_image"):
		pp(secret['content'], secret['comment_count'], secret['liked_count'])
	else:
		pp(secret['content'], secret['comment_count'], secret['liked_count'])

class Client:

	def __init__(self):
		pass

	def url(self, api):
		return "http://%s/%s" % (host, api)


	def latest_secrets(self):
		payload = urllib.urlencode({
			'school_id':self.schoolid, 'academy_id': self.academyid, 'grade': self.grade   
		})
		response = opener.open(self.url("secret/latest"), payload)
		data = response.read()
		print data
		j = json.loads(data)
		# print j
		for i in j['results']['items']:
			print_secret(i)
			#pass




	def post_secret(self, content, imagekey):
		payload = None
		if imagekey != None:
			payload = urllib.urlencode({
			'content' : content, 
			'school_id':self.schoolid, 'academy_id': self.academyid, 'grade': self.grade, 'image-key': imagekey
			})
		else:
			payload = urllib.urlencode({
			'content' : content, 
			'school_id':self.schoolid, 'academy_id': self.academyid, 'grade': self.grade
			})
		response = opener.open(self.url("secret/post"), payload)
		print response.read()

	def like_secret(self, secret_id):
		url = "http://127.0.0.1/secret/like/%s" % secret_id
		response = opener.open(url)
		print response.read()

	def cancellike_secret(self, secret_id):
		url = "http://127.0.0.1/secret/cancelLike/%s" % secret_id
		response = opener.open(url)
		print response.read()


	def remove_secret(self, secret_id):
		url = "http://127.0.0.1/secret/remove/%s" % secret_id
		response = opener.open(url)
		print response.read()

	def report_secret(self, secret_id):
		url = "http://127.0.0.1/secret/report/%s" % secret_id
		response = opener.open(url)
		print response.read()

	def fetch_verifycode(self, phone):
		response = opener.open(self.url("user/fetchVerifyCode/%s" % phone))
		print response.read()	

	def register(self, username, password, verifycode):

		payload = urllib.urlencode({
				'username' : username, 'password': password, 
				'verify-code':verifycode
			})
		response = opener.open(self.url("user/register"), payload)
		print response.read()

	def finish_register(self, schoolid, academyid, grade):
		payload = urllib.urlencode({
				'school_id':schoolid, 'academy_id': academyid, 'grade': grade
			})
		response = opener.open(self.url("user/finishRegister"), payload)
		print response.read()	        

	def signIn(self, username, password):
		payload = urllib.urlencode({
			'username' : username, 'password': password
			})		
		response = opener.open(self.url("user/signIn"), payload)
		# print response.headers
		data = response.read()
		# print data
		j = json.loads(data)
		if j.has_key('results'):
			self.schoolid = j['results']['school_id']
			self.academyid = j['results']['academy_id']
			self.grade = j['results']['grade']
		else:
			print "SignIn failed"

	def signOut(self):
		response = opener.open(self.url("user/signOut"))
		print response.read()

	def fetch_comments(self, secret_id):
		url = "http://127.0.0.1/comment/fetch/%s" % secret_id
		response = opener.open(url)
		s = response.read()
		#print s
		o = json.loads(s)
		for i in o['results']['items']:
			print i
			if not i.has_key("me"):
				#print "avatar: %s" % i['avatar_id']
				#break
				pass

	def post_comment(self, secret_id, content):
		url = "http://127.0.0.1/comment/post/%s" % secret_id
		payload = urllib.urlencode({
				'content' : content,
			})
		response = opener.open(url, payload)
		print response.read()

	def signInWithId(self, userid):
		url = "http://127.0.0.1/user/pySignIn/%s" % self.__userid
	
		response = opener.open(self.url("user/signInWithId/%s" % userid))
		# response.
		print response.read()


	def clear(self):
		url = "http://127.0.0.1/api/clearAllSession"
	
		response = opener.open(url)
		print response.read()

	def getredisvalue(self, key):
		url = "http://127.0.0.1/api/getRedisValue/%s" % key
	
		response = opener.open(url)
		print response.read()

	def getredisrangevalue(self, key):
		url = "http://127.0.0.1/api/getRedisRangeValue/%s" % key
	
		response = opener.open(url)
		print response.read()

	def getverifycode(self, phone):
		url = self.url("api/getRedisValue/u:v:code:%s" % phone)
		print url
		response = opener.open(url)
		vcode = response.read()
		return vcode.strip()

	def get_uptoken(self):
		url = self.url("image/uptoken")
		response = opener.open(url)
		data = response.read()
		print data
		r = json.loads(data)['results']
		return r['uptoken'], r['key']

	def upload_image(self, uptoken, key, image):
		localfile = "D:\\Git\\CampusSecret\\test\\" + image
		ret, err = qiniu.io.put_file(uptoken, key, localfile)
		if err is not None:
			sys.stderr.write('error: %s ' % err)
			return ""
		else:
			pass

host = host1
if len(sys.argv) > 1:
	console = True
	if sys.argv[1] == "2":
		host = host2
	else:
		host = host1

print "HOST:%s" % host


def register(c, phone):
	c.fetch_verifycode(phone)
	verifycode = c.getverifycode(phone)
	print "验证码:", verifycode
	print "注册"
	c.register(phone, 123456, verifycode)
	print "完成注册(填写学校信息)"
	c.finish_register(1001, 12, 2012)
	
def upload_image(c):
	# http://xiaoyuanmimi.qiniudn.com/5382bf5fa9ff7475?e=1401081575&token=eptRxgNhZvlghg5UtYOUhCix_SIgwLG8Dg7UqDKE:rIahlnWHINtpKqDidPIui9NT52E=
	image = "aa.jpg"
	uptoken, key = c.get_uptoken()
	print uptoken, key
	c.upload_image(uptoken, key, image)

if __name__ == "__main__":
	c = Client()
	#register(c, "12345678900")
	phone = "12345678900"
	c.signIn(phone, "123456");
	#c.post_secret("Hello secrets", None)
	c.latest_secrets()
	#c.like_secret(5)
	#c.post_comment(5, "333")
	


	#upload_image(c)
