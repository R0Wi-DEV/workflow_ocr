---
name: Bug report
about: Create a report to help us improve
title: ''
labels: bug
assignees: R0Wi

---

**Describe the bug**
A clear and concise description of what the bug is.

**System**

- App version: <VERSION>
- Nextcloud version: <VERSION>

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

**Screenshots**
If applicable, add screenshots to help explain your problem.

**Server log**

Please paste relevant content of your [ `nextcloud.log`](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/logging_configuration.html#logging) file here.  It might make sense to first decrease the [Loglevel](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/logging_configuration.html#log-level). Also, since the OCR process runs asynchronously, run your [cron.php](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/background_jobs_configuration.html#cron) before copying the logs here.

```
Paste relevant server log lines here. Make sure to trim sensitive information.
```

**Browser log**
If you're observing Browser errors, please paste your developer tools logs here. 

Help for Chrome: https://developer.chrome.com/docs/devtools/console/#view
Help for Firefox: https://firefox-source-docs.mozilla.org/devtools-user/browser_console/index.html

```
Paste your developer tools logs here. 
```

**Additional context**
Add any other context about the problem here.
