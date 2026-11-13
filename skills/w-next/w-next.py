#!/usr/bin/env python3
"""Board snapshot for the Yaup `w-next` skill.

Prints Markdown with four groups from a GitHub project board:
recently merged PRs (green), blocked tickets (red), tickets in progress or in
review (black), and the next tickets to pick up (blue).

Usage: w-next.py [--owner erme2] [--project 14] [--merged 5] [--next 4]
"""
import argparse
import json
import subprocess
import sys

PRIORITY = {"priority: blocking": 0, "priority: p0": 0, "priority: high": 1, "priority: p1": 1,
            "priority: medium": 2, "priority: low": 3}
BLOCKED = "status: blocked"


def gh(*args):
    result = subprocess.run(["gh", *args], capture_output=True, text=True)
    if result.returncode != 0:
        sys.exit(f"gh {' '.join(args)} failed: {result.stderr.strip()}")
    return json.loads(result.stdout)


def ref(content):
    repo = (content.get("repository") or "").split("/")[-1]
    return f"[{repo}#{content.get('number')}]({content.get('url')})"


def priority(labels):
    return min((PRIORITY.get(label, 4) for label in labels), default=4)


def label_text(labels):
    shown = [l.replace("priority: ", "") for l in labels if l.startswith("priority: ")]
    return f" `{shown[0]}`" if shown else ""


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--owner", default="erme2")
    parser.add_argument("--project", default="14")
    parser.add_argument("--merged", type=int, default=5)
    parser.add_argument("--next", type=int, default=4)
    args = parser.parse_args()

    board = gh("project", "item-list", args.project, "--owner", args.owner, "--limit", "500", "--format", "json")
    items = []
    for position, item in enumerate(board.get("items", [])):
        content = item.get("content") or {}
        if content.get("type") != "Issue":
            continue
        items.append({
            "status": item.get("status") or "Todo",
            "labels": item.get("labels") or [],
            "content": content,
            "position": position,
        })

    board_repos = sorted({i["content"].get("repository") for i in items if i["content"].get("repository")})
    merged = gh("search", "prs", "--owner", args.owner, "--merged", "--sort", "updated", "--order", "desc",
                "--limit", "40", "--json", "number,title,repository,url,closedAt")
    merged = [pr for pr in merged if pr["repository"]["nameWithOwner"] in board_repos]
    merged.sort(key=lambda pr: pr.get("closedAt") or "", reverse=True)

    open_items = [i for i in items if i["status"] != "Done"]
    blocked = [i for i in open_items if BLOCKED in i["labels"]]
    working = [i for i in open_items if i["status"] in ("In Progress", "In Review") and BLOCKED not in i["labels"]]
    todo = [i for i in open_items if i["status"] == "Todo" and BLOCKED not in i["labels"]]
    todo.sort(key=lambda i: (priority(i["labels"]), i["position"]))

    def line(emoji, item, extra=""):
        c = item["content"]
        return f"- {emoji} {ref(c)} {c.get('title', '')}{label_text(item['labels'])}{extra}"

    out = ["### 🟢 Recently merged"]
    out += [f"- 🟢 [{pr['repository']['name']}#{pr['number']}]({pr['url']}) {pr['title']} ({(pr.get('closedAt') or '')[:10]})"
            for pr in merged[:args.merged]] or ["- none"]
    out.append("\n### 🔴 Blocked")
    out += [line("🔴", i, f" ({i['status']})" if i["status"] != "Todo" else "") for i in sorted(blocked, key=lambda i: (priority(i["labels"]), i["position"]))] or ["- none"]
    out.append("\n### ⚫ In progress / in review")
    out += [line("⚫", i, f" ({i['status']})") for i in working] or ["- none"]
    out.append(f"\n### 🔵 Next {args.next}")
    out += [line("🔵", i) for i in todo[:args.next]] or ["- none"]
    print("\n".join(out))


if __name__ == "__main__":
    main()
