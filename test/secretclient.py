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
import hashlib

""" 
class Error 
"""
class Error:
    def __init__(self, errorcode, data):
        self.__errorcode = errorcode
        self.__data = data

    def __str__(self):
        return '<ERROR: %s> : %s' % (self.__errorcode, self.__data)

def check(e):
    if e:
        print e
        exit()

class Secret:
    def __init__(self, data):
        self.__data = data

    def __str__(self):
        d = self.__data
        content = d['content']

        return '[Secret: %s] : %s' % (d['secret_id'], content)          

""" 
class Client 
"""
class SecretClientBase(object):
    """
    constructor
    """
    def __init__(self, host):
        cookie_support = urllib2.HTTPCookieProcessor(cookielib.CookieJar())
        self.opener = urllib2.build_opener(cookie_support, urllib2.HTTPHandler)
        useragent = 'Mozilla/5.0 (Linux; U; Android 2.3.7; en-us; Nexus One Build/FRF91) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1'
        self.opener.addheaders = [("User-agent", useragent), ("Accept", "*/*")]
        self.__host = host
        self.min_secretid = 99999999999
        self.__httpcount = 0
        self.__disable_output = False

    def __del__(self):
        self.signout()

    def url(self, api):
        return "http://%s/%s" % (self.__host, api)

    def disable_output(self, disabled = True):
        self.__disable_output = disabled

    def httppost(self, api, params):
        payload = urllib.urlencode(params)
        response = self.opener.open(self.url(api), payload)
        data = response.read()
        self.__httpcount += 1
        if not self.__disable_output:
            print "-" * 80
            print "API: %s" % api, "with ", payload
        return data

    def httpget(self, api):
        response = self.opener.open(self.url(api))
        data = response.read()
        self.__httpcount += 1
        if not self.__disable_output:
            print "-" * 80
            print "API: %s" % api
        return data

    def results(self, data):
        try:
            p = data
            pos = data.find("--END--")
            if (pos >= 0):
                a = data[0: pos].strip()
                if len(a) > 0:
                    print "*" * 40
                    print a
                    print "*" * 40
                p = data[pos + 7:]
        
            j = json.loads(p)
            if j.has_key("errorCode"):
                if j['errorCode'] == 0:
                    if j.has_key("consuming"):
                        if not self.__disable_output:
                            print "[Consuming Time]: %sms" % int(j['consuming'] * 1000)
                    return j['results'], None
                else:
                    errorcoe = j['errorCode']
                    error = Error(errorcoe, data)
                    return j, error
            else:
                return None, None
        except:
            print "*" * 20 + 'Exception String' + "*" * 20
            print data
            print "*" * 20 + 'Exception String' + "*" * 20

    """
    Register
    """
    def fetch_verifycode(self, phone):
        data = self.httpget("user/fetchVerifyCode/%s" % phone)
        r, e = self.results(data)
        return r, e

    def register(self, username, password, verifycode):
        md5 = hashlib.md5()
        md5.update(password)
        password_hash = md5.hexdigest()
        payload = { 'username' : username, 'password_hash': password_hash, 'verify-code': verifycode, '_debug': 1 }
        data = self.httppost("user/register", payload)
        r, e = self.results(data)
        return r, e

    def finish_register(self, schoolid, academyid, grade):
        payload = { 'school_id':schoolid, 'academy_id': academyid, 'grade': grade }
        data = self.httppost("user/finishRegister", payload)
        r, e = self.results(data)
        return r, e

    def change_schoolinfo(self, schoolid, academyid, grade):
        payload = {  'school_id':schoolid, 'academy_id': academyid, 'grade': grade }
        data = self.httppost("user/changeInfo", payload)
        r, e = self.results(data)
        return r, e        

    def delete_user(self, userid):
        data = self.httppost('admin/deleteUser', {'user_id': userid})
        r, e = self.results(data)
        return r, e

    def get_userid(self, username):
        data = self.httpget('admin/getUserId/' + username)
        r, e = self.results(data)
        return r, e

    """
    Auth
    """
    def signin(self, username, password):
        md5 = hashlib.md5()
        md5.update(password)
        password_hash = md5.hexdigest()

        payload = { 'username': username, 'password_hash': password_hash, '_debug': 1, 'platform': 1 }
        data = self.httppost("user/signIn", payload)
        r, e = self.results(data)
        if not e:
            self.username = username
            self.schoolid = r['school_id']
            self.academyid = r['academy_id']
            self.grade = r['grade']
        return r, e

    def adminsignin(self, username, password):
        md5 = hashlib.md5()
        md5.update(password)
        password_md5 = md5.hexdigest()

        payload = { 'username': username, 'password_md5': password_md5 }
        data = self.httppost("admin/signIn", payload)
        print data
        r, e = self.results(data)
        return r, e

    def signout(self):
        data = self.httpget("user/signOut")
        print "HTTP request count: %s" % self.__httpcount
        r = self.results(data)
        return r

    """
    Secrets
    """
    def latest_secrets(self):
        data = self.httpget("secret/latest")
        r, e = self.results(data)
        if not e:
            items = r['items']
            for i in items:
                secretid = int(i['secret_id'])
                self.min_secretid = min(self.min_secretid, secretid)
        return r, e

    def print_secrets(self, secrets):
        print '-' * 30 + 'Secrets' + '-' * 30
        for secret in secrets:
            print Secret(secret)
        print '-' * 30 + 'Secrets' + '-' * 30            

    def past_secrets(self):
        data = self.httpget("secret/past/%s" % self.min_secretid)
        r, e = self.results(data)
        if not e:
            items = r['items']

            for i in items:
                secretid = int(i['secret_id'])
                self.min_secretid = min(self.min_secretid, secretid)
        return r, e

    def post_secret(self, content, backgroundindex, imagekey = None):
        payload = { 'content':content }
        if imagekey != None:
            payload['image-key'] = imagekey
        else:
            payload['background-index'] = backgroundindex
        data = self.httppost("secret/post", payload)
        return self.results(data)

    def pick_question(self):
        data = self.httpget("secret/question")
        return self.results(data)

    def like_secret(self, secretid, like = True):
        api = "like"
        if like == False:
            api = "cancelLike"

        data = self.httpget("secret/%s/%s" % (api, secretid))
        return self.results(data)

    def remove_secret(self, secretid):
        data = self.httpget("secret/remove/%s" % secretid)
        return self.results(data)

    def delete_secret(self, secretid):
        data = self.httpget("secret/delete/%s" % secretid)
        return self.results(data)       

    def report_secret(self, secretid, reason):
        data = self.httppost("secret/report/%s" % secretid, {'reason': reason})
        return self.results(data)

    def recommend_secret(self, secretid, schoollist):
        payload = { 'secret_id': secretid, 'school_list': schoollist }
        data = self.httppost("admin/recommendSecret", payload)
        r, e = self.results(data)
        return r, e

    """
    Comments
    """
    def fetch_comments(self, secretid):
        data = self.httpget("comment/fetch/%s" % secretid)
        return self.results(data)

    def post_comment(self, secretid, content):
        payload = { 'content' : content  }
        data = self.httppost("comment/post/%s" % secretid, payload)
        return self.results(data)

    """ composed operation """
    def add_comment(self, secretid, content):
        r, e = self.fetch_comments(secretid)
        if not e:
            return self.post_comment(secretid, content)
        else:
            return r, e        

    def like_comment(self, secretid, commentid, floor, like = True):
        api = "like"
        if not like:
            api = "cancelLike"
        data = self.httpget("comment/%s/%s/%s?floor=%s" % (api, secretid, commentid, floor))
        return self.results(data)

    def report_comment(self, secretid, commentid, reason):
        data = self.httppost("comment/report/%s/%s" % (secretid, commentid), {'reason': reason})
        return self.results(data)

    # def report_secret(self, secretid, reason):
    #     data = self.httppost("secret/report/%s" % secretid, {'reason': reason})
    #     return self.results(data)

    def delete_comment(self, secretid, commentid):
        data = self.httpget("comment/delete/%s/%s" % (secretid, commentid))
        return self.results(data)

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
        response = self.opener.open(self.url("image/uptoken"))
        data = response.read()
        r, e = self.results(data)
        return r, e


    def upload_image(self, uptoken, key, image):
        localfile = image
        ret, err = qiniu.io.put_file(uptoken, key, localfile)
        if err is not None:
            sys.stderr.write('error: %s ' % err)
            return ""
        else:
            pass
    def download_image(self, url, name = "TEST", delete = True):
        response = self.opener.open(url)
        filename = name + ".jpg"
        f = file(filename, "wb")  
        f.write(response.read())  
        f.close()
        if not os.path.exists(filename):
            raise Exception
        os.remove(filename)

    """
    Notice
    """
    def fetch_notice(self, all = False):
        api = "notice/fetch"
        if all:
            api = "notice/fetch/all"
        data = self.httpget(api)
        r, e = self.results(data)
        return r, e

    def query_notice(self):
        data = self.httpget("notice/query")
        r, e = self.results(data)
        return r, e

    def clear_notice(self):
        r, e = self.fetch_notice()
        check(e)
        for c in r['items']:
            self.fetch_comments(c['secret_id'])
        
    """
    Shared
    """
    def shared_latest(self, schoolid):
        data = self.httpget("share/latest/" + str(schoolid))
        r, e = self.results(data)
        return r, e

    def shared_past(self, schoolid, minid):
        data = self.httpget("share/past/" + str(schoolid) + "/" + str(minid))
        r, e = self.results(data)
        return r, e

    """
    Others
    """
    def feedback(self, content, contact = None):
        payload = {'content':content}
        if contact:
            payload['contact'] = contact
        data = self.httppost('feedback/post', payload)
        r, e = self.results(data)
        return r, e

    def schools(self, province):
        data = self.httpget('school/list/%s' % province)
        r, e = self.results(data)
        return r, e

    def academy(self, schoolid):
        data = self.httpget('school/academy/%s' % schoolid)
        r, e = self.results(data)
        return r, e

    def version(self, versioncode):
        data = self.httpget('common/version/android/%s' % versioncode)
        r, e = self.results(data)
        return r, e

    def create_version(self, version, policy):
        payload = { 'version' : version, 'policy': policy }
        data = self.httppost("admin/setVersionPolicy/android", payload)
        r, e = self.results(data)
        return r, e    

    def token(self, type, account, token):
        payload = { 'type': 1, 'account': account, 'token': token }
        data = self.httppost('user/token', payload)
        r, e = self.results(data)
        return r, e

    def test(self):
        data = self.httpget('api/test')

    #####################################################################
    """
    1.1
    """
    def mark_secret(self, secretid):
        data = self.httpget('secret/mark/' + str(secretid))
        r, e = self.results(data)
        return r, e
        
    def unmark_secret(self, secretid):
        data = self.httpget('secret/unmark/' + str(secretid))
        r, e = self.results(data)
        return r, e        

class SecretClient(SecretClientBase):

    def __init__(self, host):
        if host == None:
            host = open('host', 'r').readline()
        print "HOST: " + host
        super(SecretClient, self).__init__(host)



def contains_item(items, key, value):
    for item in items:
        if item.has_key(key) and item[key] == value:
            return True
    return False

def max_secret_id(items):
    r = 0
    for item in items:
        secret_id = int(item['secret_id'])
        if secret_id > r:
            r = secret_id
    return r

def min_secret_id(items):
    r = 999999999999
    for item in items:
        secret_id = int(item['secret_id'])
        if secret_id < r:
            r = secret_id
    return r

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

"""
__main__
"""
if __name__ == "__main__":
    print 'Run cases, please.'
