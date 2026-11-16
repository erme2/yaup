#!/usr/bin/env python3
# Posts one Slack message as the Yaup agents bot. Used by the agent runners, which have no Slack connector.
#
# Usage: slack_post.py <dm|channel> < message.txt
#   dm       sends to the owner (SLACK_OWNER_DM in ~/.config/yaup/agents.env): opens the bot's DM with the owner.
#   channel  sends to the #sweegly channel (SLACK_CHANNEL). The bot must be invited to it.
# The token is read from ~/.config/yaup/slack-token (chmod 600, outside the repo). Nothing is printed except errors.
import json
import os
import sys
import urllib.parse
import urllib.request

TOKEN_FILE = os.path.expanduser("~/.config/yaup/slack-token")
CONFIG_FILE = os.path.expanduser("~/.config/yaup/agents.env")


def config():
    values = {}
    with open(CONFIG_FILE) as handle:
        for line in handle:
            line = line.strip()
            if line and not line.startswith("#") and "=" in line:
                key, value = line.split("=", 1)
                values[key.strip()] = value.strip()
    return values


def call(method, token, params):
    data = urllib.parse.urlencode(params).encode()
    request = urllib.request.Request(
        "https://slack.com/api/" + method,
        data=data,
        headers={"Authorization": "Bearer " + token},
    )
    with urllib.request.urlopen(request, timeout=30) as response:
        reply = json.load(response)
    if not reply.get("ok"):
        raise SystemExit("slack " + method + " failed: " + str(reply.get("error")))
    return reply


def main():
    if len(sys.argv) != 2 or sys.argv[1] not in ("dm", "channel"):
        raise SystemExit("usage: slack_post.py <dm|channel> < message.txt")
    with open(TOKEN_FILE) as handle:
        token = handle.read().strip()
    text = sys.stdin.read().strip()
    if not text:
        raise SystemExit("empty message")
    values = config()
    if sys.argv[1] == "dm":
        opened = call("conversations.open", token, {"users": values["SLACK_OWNER_DM"]})
        channel = opened["channel"]["id"]
    else:
        channel = values["SLACK_CHANNEL"]
    call("chat.postMessage", token, {"channel": channel, "text": text, "unfurl_links": "false"})
    print("sent to " + sys.argv[1])


if __name__ == "__main__":
    main()
