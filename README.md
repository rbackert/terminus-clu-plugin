# Terminus CLU client

A terminus plugin to create pull requests based on composer.lock updates.

## Requirements
Depends on Terminus Build Tools, and only works for a Build Tools managed site.

## Installation

- `cd ~/.terminus/plugins`
- `git clone https://github.com/pantheon-systems/terminus-clu-plugin.git` 
- `cd terminus-clu-plugin`
- `composer install --no-dev`

## Commands

`terminus project:clu`

also available:

`terminus project:clu:git <https git repository url with username and token>`

`terminus project:pull-request:list`

`terminus project:pull-request:create`

`terminus project:pull-request:close <pr-id>`
