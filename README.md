# Terminus CLU client

A terminus plugin to create pull requests based on composer.lock updates.

## Requirements
Depends on Terminus Build Tools, and only works for a Build Tools managed site.

## Installation

`cd ~/.terminus/plugins && git clone https://github.com/aaronbauman/terminus-clu-plugin.git` 

## Commands


`terminus project:clu`

also available:

`terminus project:pull-request:list`

`terminus project:pull-request:create`

`terminus project:pull-request:close <pr-id>`
