# fluentu-leadbox
Simple plugin for generating PDFs from posts and emailing download links

## Plugin Configuration

The plugin has its own `config.php` in the plugin directory. This file defines `INSERT_POINTS` for leadbox placement and is tracked in git.

API keys and service credentials are defined separately in `wp-config.php` (not in this repo). The following constants must be set there:

- `PRINTFRIENDLY_API_KEY`
- `PRINTFRIENDLY_CSS_URL`
- `DITTOFEED_API_URL`
- `DITTOFEED_WRITE_KEY`
- `DITTOFEED_APP_ENV`
- `DUAL_WRITE_ENABLED` (optional, for migration)
- `EO_API_URL`, `EO_API_KEY`, `EO_LIST_ID` (only needed if `DUAL_WRITE_ENABLED` is true)

## Updating the Plugin

1. Push changes to GitHub
2. Manually package the plugin as a `.zip` file
3. Upload/install the `.zip` via WordPress admin or directly on the server

## Updating wp-config.php

1. Connect to the blog server via SSH (the IP of the autoscaling instance changes automatically)
    ssh -i ~/.ssh/fluentu/ec2-user.key ec2-user@3.88.43.120
2. Run `sudo su` before editing files
3. Edit `wp-config.php` directly on the server via SSH
4. Updating files on the autoscaling instance works because it is connected to an NFS server
