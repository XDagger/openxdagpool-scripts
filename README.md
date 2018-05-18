# Contents
This repository contains scripts that are run as the pool user. Pool user runs the xdag pool daemon itself, cron schedule and nginx PHP FPM pool.

# Expected skills
This readme can't go in-depth into every step necessary, you are expected to have good knowledge of linux / unix administration as well as basics of computer programming, and also good understanding of
how xdag pool daemons work in general and be familiar with their settings. This readme assumes your IP is already whitelisted on the main network.

# Full setup
On a fresh ubuntu server 16.04 LTS installation, perform the following steps, initially as `root`:
1. set your system timezone to `UTC`, execute `dpkg-reconfigure tzdata` and choose `UTC`
2. `apt-get install git nginx php7.0-fpm php7.0-cli build-essential libssl-dev gcc`
3. `adduser pool`
4. `su pool`
5. `cd /home/pool`
6. `git clone https://github.com/XDagger/openxdagpool-scripts.git scripts`
7. `git clone https://github.com/XDagger/xdag.git xdag1`
8. `git clone https://github.com/XDagger/xdag.git xdag2` (TWO separate working copies are necessary for proper pool operation)
9. `echo -n 1 > ~/CURRENT_XDAG`
10. make sure `/var/www/pool` exists and is owned by `pool`
11. make sure a new php7.0-fpm pool is running as user `pool`
12. make sure nginx config allows execution of `php` files

Once this is done, compile both xdag1 and xdag2 using `make`. Compile as user `pool`. Execute xdag1 with proper pool command line as user `pool`,
for example `TZ=GMT ./xdag -d -p 95.105.233.208:16775 -P 95.105.233.208:13654:20000:2000:450:1:1:1:1`. Instead of `95.105.233.208` use your own external IP.
Set up your password, type random keys (at least 3 lines of random keys), wait for the deamon to fully sync with the newtwork.
Then quit the daemon by typing `terminate`.

Enter the `xdag2/client` directory (still as user `pool`) and copy `wallet.dat`, `dnet_key.dat` from `xdag1/client`.
Symlink `storage` folder by typing `ln -s /home/pool/xdag1/client/storage`. Verify by typing `ls -la`.

Once all is done, go to templates directory in this repository, and COPY all files to both `xdag1/client` and `xdag2/client`. Edit the `xdag_run.sh` file in both folders with *your* pool settings.

Re-execute xdag1 using `./xdag_run.sh` in `xdag1/client` folder without the `-r` option (script will ask). Wait for the pool to start up and load blocks from the storage.

Next type `crontab -e` as user `pool` and enter the following cron schedule:
```
*/5 * * * * /bin/bash /home/pool/scripts/xdag_dump_fastdata.sh
3 */3 * * * /bin/bash /home/pool/scripts/xdag_dump_slowdata.sh
40 */3 * * * /bin/bash /home/pool/scripts/xdag_update_whitelist.sh
50 2 * * * /bin/bash /home/pool/scripts/xdag_delete_tmp_files.sh

```
Done. Your software should now periodically export necessary files to the nginx public webroot directory and update the pools `netdb-white.txt`.

As a last thing, copy `wwwscripts/balance.php` into `/var/www/pool` directory. Make sure the file is owned by `pool` user and is executable.

# Partial setup
If you already run your pool daemon by any means, only necessary additions for the [OpenXDAGPool](https://github.com/XDagger/openxdagpool) to work properly
are the four CRON scripts mentioned in the chapter above (`xdag_dump_fastdata.sh`, `xdag_dump_slowdata.sh`, `xdag_update_whitelist.sh` and `xdag_delete_tmp_files.sh`).
The last one (`xdag_delete_tmp_files.sh`) is only required to keep your hard drive space in check, by deleting unnecessary tmp files created by the pool daemon.

Tweak the scripts to export data from your pool daemon. Nginx is required so these text files are downloadable by [OpenXDAGPool](https://github.com/XDagger/openxdagpool).

As for balance checking, you are not required to use `wwwscripts/balance.php`, you can use any other balance checker that *contains* compatible output (`x.xxxxxxxxx` - the address in question balance with 9 decimal places) and
can accept XDAG address in question as a GET / route parameter. The balance checker URL is configurable in [OpenXDAGPool's](https://github.com/XDagger/openxdagpool) `.env` file.

Make sure your system timezone is set to `UTC`. This helps to keep payouts and blocks export imported every ~4 hours. [OpenXDAGPool](https://github.com/XDagger/openxdagpool) runs internally in `UTC` timezone.

# Usage
To use these scripts, always `su pool`,  `cd`, `cd scripts` and then run `./xdag_....` as you need, or execute `./xdag_....` in particular xdag directory to interact with desired xdag daemon.

NEVER delete your `xdag.log*` file, only if you are certain the [OpenXDAGPool](https://github.com/XDagger/openxdagpool) has already imported all payouts and found blocks in that log file. If not, you will lose some of your payouts and found blocks history. It is safe to delete `xdag.log*` file for currently unused daemon that's not been in use for more than 3 days, assuming all services (cron exports and website imports) are running properly. See next section for details.

If your pool is already running for a long time and you have your all-time `xdag.log*` file(s), tweak the `generate_last_days_regex.php` file by uncommenting marked line, then wait for OpenXDAG pool to import your payouts and found blocks. This happens every 3 hours. After this is done, you can safely comment the line back to keep importing only the latest payouts and found blocks.

# Notes on xdag log files
You can archive old log files at runtime to another partition, to conserve disk space. Execute `mv xdag.log xdag.log.20180516` or similar unique name in current `xdag` folder. Leave the old file in-place for 3 days. After this period, the file was definitely grepped by [OpenXDAGPool-scripts](https://github.com/XDagger/openxdagpool-scripts), and can be safely moved elsewhere.
`xdag_dump_slowdata.sh` script always scans `xdag.log*` files in each `xdag` folder, making sure your old log files can be imported before you move them somewhere else.

# Pool updates
Update pool by updating and running xdag that is currently NOT stored in `CURRENT_XDAG`. `cd` to desired xdag directory as user `pool` and type `./xdag_update.sh` or `git pull` and `make` manually. Run `./xdag_run.sh`, run daemon with `-r` option.
This will allow the program to load blocks while the old pool is running.
When done (check using `./xdag_console.sh` and `state` and `stats` commands), terminate the old daemon marked by `CURRENT_XDAG` using `terminate` in it's console. ONLY THEN `echo -n 2 > ~/CURRENT_XDAG` or `1` depending on
what software is main now. You may pause cron by commenting out the lines in order to not export data from already dead daemon.

After new daemon picks up, uncomment cron lines, verify `CURRENT_XDAG` contains the correct daemon number (no newline at the end! use `echo -n` as described), and your update is complete.
