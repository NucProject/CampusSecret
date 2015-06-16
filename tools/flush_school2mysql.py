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
import HTMLParser
import time
from pyquery import PyQuery as pq

reload(sys)
sys.setdefaultencoding('utf8')
import MySQLdb

conn = None
try:
    conn=MySQLdb.connect(host='localhost',user='root',passwd='root',db='csdb',port=3306, charset="utf8")
except:
    pass


path = "E:\\shared\\mimi\\院系列表"
path = path.decode("utf8")

subpaths = os.walk(path).next()[1]
cur=conn.cursor()

a = []
pr = 0
for p in subpaths:
    pr += 1
    prov = path + "\\" + p

    a.append({pr: p})

    continue
    files = os.walk(prov).next()[2]
    for f in files:
        ps = f.split("-")
        schoolid = ps[0].strip()

        if len(ps) == 3:
            name = ps[2].strip().encode("utf8")
            name2 = ps[1].strip().encode("utf8")
            s = "insert into school(school_id, name, name2, province_tag) values(%s, '%s', '%s', %s)" % (schoolid, name, name2, pr)
            cur.execute(s)
        else:
            name = ps[1].strip().encode("utf8")
            
            s = "insert into school(school_id, name,  province_tag) values(%s, '%s',  %s)" % (schoolid, name, pr)
            cur.execute(s)


print json.dumps(a, ensure_ascii=False)
cur.close()
conn.close()


