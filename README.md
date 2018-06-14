# Contents
This repository contains scripts that are run as the pool user. Pool user runs the xdag pool daemon itself, cron schedule and nginx PHP FPM pool.

# Expected skills
This readme can't go in-depth into every step necessary, you are expected to have good knowledge of linux / unix administration as well as basics of computer programming, and also good understanding of
how xdag pool daemons work in general and be familiar with their settings. This readme assumes your IP is already whitelisted on the main network.

# Full setup
On a fresh ubuntu server 16.04 LTS installation, perform the following steps, initially as `root`:
1. set your system timezone to `UTC`, execute `dpkg-reconfigure tzdata` and choose `UTC`
2. `apt-get install git nginx php7.0-fpm php7.0-cli php7.0-mysql php7.0-opcache build-essential libssl-dev gcc mysql-client mysql-server`. Set `php.ini` to your preference, set `memory_limit` at least `512M`, make sure functions `exec` and `passthru` are not disabled, and make sure the `open_basedir` directive is empty.
3. `adduser pool`
4. `su pool`
5. `cd /home/pool`
6. `mkdir storage`
7. `git clone https://github.com/XDagger/openxdagpool-scripts.git scripts`
8. `git clone https://github.com/XDagger/xdag.git xdag1`
9. `git clone https://github.com/XDagger/xdag.git xdag2` (TWO separate working copies are necessary for proper pool operation)
9. `echo -n 1 > ~/CURRENT_XDAG`
10. go to checked out `scripts` directory and `cp CONFIG.sh.EXAMPLE CONFIG.sh`. Edit the file and set appropriate values.
11. go to `shell_templates` directory in this repository, and COPY all files to both `xdag1/client` and `xdag2/client`. Edit the `xdag_run.sh` file in both folders with *your* pool settings. Edit the
`xdag_pool_forward_enable.sh` and `xdag_pool_forward_disable.sh` scripts in both folders with your WAN IP and miners port.
12. `ln -s /home/pool/storage /home/pool/xdag1/client/storage`
13. `ln -s /home/pool/storage /home/pool/xdag2/client/storage`
14. make sure `/var/www/pool` exists and is owned by `pool`
15. make sure a new php7.0-fpm pool is running as user `pool`
16. make sure nginx config allows execution of `php` files
17. copy `engine/config.php.EXAMPLE` to `engine/config.php`, read the file and set appropriate values. Configure the mysql server and create a database according to comments. Create one
database table given in `engine/db_schema.sql`

Once this is done, compile both xdag1 and xdag2 using `make` in the `client` folder. Compile as user `pool`. Execute xdag1 by running `./xdag_run.sh` in `client` folder.
You don't have to run with the `-r` flag for the first time.
Set up your password, type random keys (at least 3 lines of random keys), wait for the deamon to fully sync with the newtwork.

Enter the `xdag2/client` directory (still as user `pool`) and copy `wallet.dat`, `dnet_key.dat` from `xdag1/client`.

Go to `/home/pool/scripts/engine` directory (as user `pool`) and execute `php core.php livedata`. You should see JSON with pool statistics, verifying the setup is correct.
Execute `php core.php blocks gather`. Wait for the command to complete. Type `crontab -e` as user `pool` and enter the following cron schedule:
```
* * * * * /usr/bin/php /home/pool/scripts/engine/core.php blocks gather >> /dev/null 2>&1
* * * * * /usr/bin/php /home/pool/scripts/engine/core.php blocks inspect >> /dev/null 2>&1
0 0 * * * /usr/bin/php /home/pool/scripts/engine/core.php blocks gatherAll >> /dev/null 2>&1
0 0 * * * /usr/bin/php /home/pool/scripts/engine/core.php blocks inspectAll >> /dev/null 2>&1
40 */3 * * * /bin/bash /home/pool/scripts/xdag_update_whitelist.sh
50 */3 * * * /bin/bash /home/pool/scripts/xdag_archive_logs.sh
50 2 * * * /bin/bash /home/pool/scripts/xdag_delete_tmp_files.sh
```
If your PHP installation has different path, enter appropriate values.

If you want the scripts to automatically configure `iptables` so that connections to pool port are blocked for all IPs except IPs in the whitelist,
make sure you set the `CONFIG.sh` values appropriately, and then delete this line from the `pool` user's cron schedule:
```
40 */3 * * * /bin/bash /home/pool/scripts/xdag_update_whitelist.sh
```
Add the same line as the only cron schedule for `root`. The script can only manage `iptables` rules as `root` user. If you don't set up the config file properly,
or you don't run the `xdag_update_whitelist.sh` script as root, it will only update your whitelist and not touch your `iptables` rules. If you use `ufw`,
disable it or tweak it so it doesn't interfere with generated rules.

Done. Your software should now periodically inspect new found blocks, update `netdb-white.txt` for the pools, manage firewall, and archive xdag log files.

As a last thing, copy `www_scripts/core_call.php` into `/var/www/pool` directory. Make sure the file is owned by `pool` user and is executable.

# Partial setup
If you already run your pool daemon by any means, only necessary additions for the [OpenXDAGPool](https://github.com/XDagger/openxdagpool) to work properly
are the CRON scripts mentioned in the chapter above. The scripts will automatically process your whole pool history (based on pool wallet)
and start processing only the latest data from then on.

`xdag_delete_tmp_files.sh` is only required to keep your hard drive space in check, by deleting unnecessary tmp files created by the pool daemon. You don't need to use this script if you use `-z RAM` to run your daemon.
`xdag_archive_logs.sh` is only required, if you want the scripts to archive and `gzip` xdag log files larger than 100MB periodically.

Copy `engine/config.php.EXAMPLE` to `engine/config.php` and set appropriate values.
Nginx or other web server is required so [OpenXDAGPool](https://github.com/XDagger/openxdagpool) is able to connect to the pool engine.

Make sure your system timezone is set to `UTC`.

# Usage
To use these scripts, always `su pool`, `cd`, `cd scripts` and then run `./xdag_....` as you need, or execute `./xdag_....` in particular xdag directory to interact with desired xdag daemon.

# Pool updates
Update pool by updating and running xdag that is currently NOT stored in `CURRENT_XDAG`. `cd` to desired xdag directory as user `pool` and type `./xdag_update.sh` or `git pull` and `make` manually. Run `./xdag_run.sh`, run daemon with `-r` option.
This will allow the program to load blocks while the old pool is running.
When done (check using `./xdag_console.sh` and `state` and `stats` commands), terminate the old daemon marked by `CURRENT_XDAG` using `terminate` in it's console. Make sure it is terminated by using `./xdag_dump_executables.sh`.
ONLY THEN `echo -n 2 > ~/CURRENT_XDAG` or `1` depending on what software is main now. You may pause cron by commenting out the lines in order to not export data from already dead daemon.

After new daemon picks up, uncomment cron lines, verify `CURRENT_XDAG` contains the correct daemon number (no newline at the end! use `echo -n` as described), and your update is complete. Notice you should block your miners port
while your new node syncs with the network.

If you want to redirect miners to another pool while your new node syncs with network (to avoid any mining downtime), do as follows:

1. install socat (`apt-get install socat`)
2. once the new daemon finishes loading blocks from storage, but *before* you type run, `terminate` the old daemon. Make sure it is terminated by using `./xdag_dump_executables.sh`. Execute `./xdag_pool_forward_enable.sh`. This will redirect your miners to another pool.
3. when the new pool SYNCS UP, simply execute `./pool_forward_disable.sh`

Done. This method makes sure your miners won't have downtime, and you can always take all of your miners back. Requirement is that the pool you are redirecting to must have free miner slots, and must have big enough miners per IP limit, and connections per address limit to accomodate your miners.

# Diagnostic / useful scripts
Use `xdag_peers_block.sh` to temporarily block all pool's peers (other nodes). You may unblock nodes you desire to sync with using `route del -host IP reject`. Once you are done, you can unblock connections to other pool nodes in the whitelist by running `xdag_peers_unblock.sh`. These scripts must be run as `root`.

The `xdag_test_whitelist.sh` script can be used to test each pool port in the whitelist, to see if it is open or closed from pool's point of view. This script requires `netcat` to be installed.
