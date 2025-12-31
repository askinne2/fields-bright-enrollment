#!/bin/bash
# Jekyll Local Development Server
# This script sets up the environment and starts Jekyll

cd "$(dirname "$0")"

# Initialize rbenv
eval "$(rbenv init - zsh)"

# Ensure we're using the correct Ruby version
rbenv local 3.3.0

# Check if port 4000 is in use and kill any existing Jekyll processes
PORT=4000
if lsof -ti:$PORT > /dev/null 2>&1; then
    echo "Port $PORT is already in use. Attempting to free it..."
    lsof -ti:$PORT | xargs kill -9 2>/dev/null
    sleep 1
    if lsof -ti:$PORT > /dev/null 2>&1; then
        echo "Warning: Could not free port $PORT. Trying alternative port 4001..."
        PORT=4001
    else
        echo "Port $PORT is now available."
    fi
fi

# Start Jekyll server
echo "Starting Jekyll server..."
echo "The site will be available at: http://localhost:$PORT/fields-bright-enrollment/"
echo "Press Ctrl+C to stop the server"
echo ""

bundle exec jekyll serve --livereload --port $PORT

