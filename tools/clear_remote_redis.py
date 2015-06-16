#!/usr/bin/env python
# -*- coding: utf-8 -*-

__author__ = 'healer'
import sys
import os
import urllib
import urllib2
import cookielib

user_agent = 'Mozilla/5.0 (Linux; U; Android 2.3.7; en-us; Nexus One Build/FRF91) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1'

cookie_support = urllib2.HTTPCookieProcessor(cookielib.CookieJar())
opener = urllib2.build_opener(cookie_support, urllib2.HTTPHandler)
opener.addheaders = [("User-agent", user_agent), ("Accept", "*/*")]
host = "182.92.80.195"
response = opener.open("http://%s/admin/signIn" % host, urllib.urlencode({"username": "yuzhongmin", "password_md5": "e10adc3949ba59abbe56e057f20f883e"}))
print response.read()
response = opener.open("http://%s/redis/clearAllCache" % host)
print response.read()

response = opener.open("http://%s/admin/signOut" % host)
print response.read()
	