#!/usr/bin/env bash
# Checks the rules that live in the web server, which the test suite cannot see.
#   docker/smoke-test.sh IMAGE
set -euo pipefail

image="$1"
port=18080
url="http://127.0.0.1:${port}"

container=$(docker run --detach --rm --publish "${port}:80" \
    --env SERVER_NAME=:80 \
    --env APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" \
    "$image")
trap 'docker stop "$container" > /dev/null' EXIT

for _ in $(seq 1 30); do
    curl --silent --fail --output /dev/null "${url}/robots.txt" && break
    sleep 1
done

headers=$(curl --silent --show-error --head "${url}/robots.txt")
grep --quiet --ignore-case '^strict-transport-security: max-age=' <<< "$headers"
grep --quiet --ignore-case '^x-content-type-options: nosniff' <<< "$headers"

response=$(head -c 20000 /dev/zero | tr '\0' 'a' | curl --silent --show-error \
    --write-out '\n%{http_code} %{content_type}' \
    --header 'Content-Type: application/json' --data-binary @- \
    "${url}/api/v1/bots/unknown/messages")

grep --quiet '"message"' <<< "$response"
grep --quiet '^413 application/json$' <<< "$response" || { echo "Oversized request got: ${response}" >&2; exit 1; }

echo "The web server rules hold."
