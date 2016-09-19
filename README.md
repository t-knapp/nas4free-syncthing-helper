# nas4free-syncthing-helper
Automatically updates syncthing file syncronisation on NAS4Free embedded systems because build-in auto-update function does not work because SSL Certs are not accessible for some reason.

# Requirements
- Webserver running local on NAS4Free system
- 'Command Script' Line on NAS4Free system

The webserver is needed to use syncthings `-upgrade-to=URL` command line argument.

## Command Script entry type PostInit
`/path/to/start/script/start-syncthing.php >> /path/to/log/file/start-syncthing.php.log &`

This scripts updates also to major versions, so you wont tell syncthing to do so by hand.
