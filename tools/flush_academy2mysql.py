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

pr = 0
for p in subpaths:
    pr += 1
    prov = path + "\\" + p

    files = os.walk(prov).next()[2]
    for f in files:
        ps = f.split("-")
        schoolid = ps[0].strip()

        


cur.close()
conn.close()


