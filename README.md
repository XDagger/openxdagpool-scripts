# Contents
This repository contains scripts that are run as the pool user. Pool user runs the xdag pool daemon itself, cron schedule and nginx PHP FPM pool.

# Skills required
This readme can't go in-depth into every step necessary, you are expected to have good knowledge of linux / unix administration as well as basics of computer programming, and also good understanding of
how xdag pool daemons work in general and be familiar with their settings. This readme assumes your IP is already whitelisted on the main network.

# Setup
On a fresh ubuntu server 16.04 LTS installation, perform the following steps, initially as `root`:
1. `apt-get install git nginx php7.0-fpm php7.0-cli build-essential`
2. `adduser pool`
3. `su pool`
4. `cd /home/pool`
5. `git clone https://github.com/kbs1/openxdagpool-scripts.git scripts`
6. `git clone https://github.com/jonano614/xdag xdag1`
7. `git clone https://github.com/jonano614/xdag xdag2` (TWO separate working copies are necessary for proper pool operation)
8. `echo -n 1 > CURRENT_XDAG`
9. make sure `/var/www/default` exists and is owned by www-data
10. make sure php7.0-fpm pool is running as user `pool`
11. make sure nginx config allows execution of `php` files

Once this is done, compile both xdag1 and xdag2 using `make`. Execute xdag1 with proper pool command line,
for example `TZ=GMT ./xdag -d -p 95.105.233.208:16775 -P 95.105.233.208:13654:20000:0.5:1:1:200:0.5`. Set up
your password, type random keys, wait for the deamon to fully sync with the newtwork. Then quit the daemon by typing `terminate`.

Enter the `xdag2/cheatcoin` directory and copy `wallet.dat`, `dnet_key.dat` from `xdag1/cheatcoin`. Symlink `storage` folder by typing `ln -s /home/pool/xdag1/cheatcoin/storage`. Verify by typing `ls -la`.

Once all is done, go to templates directory in this repository, and COPY all files to both `xdag1/cheatcoin` and `xdag2/cheatcoin`. EDIT the `xdag_run.sh` file in both folders with *your* pool settings.

Re-execute xdag1 using `xdag_run.sh` in `xdag1/cheatcoin` folder without the `-r` option (script will ask). Wait for the pool to start up and load blocks from the storage.

Next type `crontab -e` and enter the following cron schedule:
```
*/5 * * * * /bin/bash /home/pool/scripts/xdag_dump_fastdata.sh
3 */3 * * * /bin/bash /home/pool/scripts/xdag_dump_slowdata.sh
50 2 * * * /bin/bash /home/pool/scripts/xdag_delete_tmp_files.sh

```
Done. Your software should now periodically export necessary files to the nginx public webroot directory.

As a last thing, copy `wwwscripts/balance.php` into `/var/www/default` directory. Make sure the file is owned by `pool` user and is executable.

# Usage
To use these scripts, always `su pool; cd` and then run `./xdag_....` as you need, or execute `./xdag_....` in particular xdag directory to interact with desired xdag daemon.

# Pool updates
Update pool by updating and running xdag that is currently NOT stored in `CURRENT_XDAG`. Cd to directory and type `./xdag_update.sh` or git pull manually. Run `./xdag_run.sh`, run daemon with `-r` option.
This will allow the program to load blocks while the old pool is running.
When done (check using `./xdag_console.sh` and `state` and `stats` commands), terminate the old daemon marked by `CURRENT_XDAG` using `terminate` in it's console. ONLY THEN `echo -n 2 > CURRENT_XDAG` or `1` depending on
what software is main now. You may pause cron by commenting out the lines in order to not export data from already dead daemon.

After new daemon picks up, uncomment cron lines, verify CURRENT_XDAG contains the correct daemon number (no newline at the end! use `echo -n` as described), and your update is complete.
