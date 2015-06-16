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


f = codecs.open("cases.txt", "r", "utf8")
lines = f.readlines()
f.close()

agents = {}


def escape(content):
    return content.replace('\\n', '\n')

def create_agent(alias, username, password):
    global agents
    sc = SecretClient(host)
    sc.signin(username, password)

    agents[alias] = sc

def post_secret(alias, content, background):
    global agents
    agent = agents[alias]
    r, e = agent.post_secret(escape(content), background)

    if not e:
        secret = r['secret']
        return secret['secret_id']
    else:
        print r, e
        raise NameError

def post_comment(alias, secretid, content):
    global agents
    agent = agents[alias]
    agent.post_comment(secretid, escape(content))

current_secret_id = 0

for line in lines:
    line = line.strip()
    if len(line) == 0:
        continue
    p = line.split(":")

    if p[0].startswith("a"):
        create_agent(p[0], p[1], p[2])
        continue
    
    if p[0] == "s":
        content = line[len(p[1]) + 3:]
        
        c = content.encode("utf8")
        r = random.choice(range(1, 30))
        current_secret_id = post_secret(p[1], c, r)
        continue
    if p[0] == "c":
        content = line[len(p[1]) + 3:]
        post_comment(p[1], current_secret_id, content.encode("utf8"))
        continue


for i in agents:
    agents[i].signout()

