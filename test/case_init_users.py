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
import time
#from datetime import *
from secretclient import *


host1 = "127.0.0.1"             # Localhost
host2 = "182.92.80.195"         # Aliyun
console = False

host = host1
if len(sys.argv) > 1:
    console = True
    if sys.argv[1] == "2":
        host = host2
    else:
        host = host1

print "HOST: %s" % host
########################################################################

sc = SecretClient(host)
 
phone=19000000000
def register(sc, phone,school_id, academy_id):
    verifycode=sc.fetch_verifycode(phone)
    sc.register(phone, '123456', verifycode)
    sc.finish_register(school_id, academy_id, 2013) 

    

for i in range(1, 35):
    schools, e = sc.schools(i)
    for j in schools:
        school_id = j['school_id']
        academies, e = sc.academy(school_id)
        if len(academies) > 0:
            for z in academies:
                academy_id = z['academy_id']
                register(sc, phone, school_id, academy_id)
                phone+=1
                
        time.sleep(0.1)
    time.sleep(1)
           

  

#sc.register('username', '123456')

