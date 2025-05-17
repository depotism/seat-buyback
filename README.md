# seat-buyback
[![Latest Stable Version](https://img.shields.io/packagist/v/depotism/seat-buyback?style=flat-square)]()
![](https://img.shields.io/badge/SeAT-5.0.x-blueviolet?style=flat-square)
![](https://img.shields.io/github/license/Depotism/seat-buyback?style=flat-square)

A module for [SeAT](https://github.com/eveseat/seat) that makes your life with corporation buyback programs a lot easier.

In case of problems please contact me via EVE-Online message or over the seat discord: `depotism`

## Screenshots
This repro is based on [H4zz4rdDev/seat-buyback](https://github.com/H4zz4rdDev/seat-buyback)'s seat buyback plugin. 

So what is the difference?
1. I work with Seat 5!
2. We use the Seat 5 [SeAT Price Provider System Core](https://github.com/recursivetree/seat-prices-core) - i would add the [EvePraisal Price Provider](https://github.com/recursivetree/seat-prices-evepraisal)
3. You can set the price provider of your choice! 
4. ... even on per item base!
5. In H4zz4rd's version you had to add item by item manually. 
6. ... here you can even paste a list of items
7. ... or: even add a whole category of items!
8. tired of using compressed random ore values? No Problem: pay the buyback by reprocessed value! 

## Screenshots
![contractoverview](https://raw.githubusercontent.com/depotism/seat-buyback/refs/heads/master/img/contract_overview.png)

![itemconfig](https://raw.githubusercontent.com/depotism/seat-buyback/refs/heads/master/img/item_config.png)

![itemoverview](https://raw.githubusercontent.com/depotism/seat-buyback/refs/heads/master/img/item_overview.png)


## Permissions
There are three different types of permissions you can give to your members:

#### Request
This is the default right a corp mate needs to access the buyback module. This permission includes the "Request" and "My Contracts" section.
#### Contract
This permission is for all corp mates that are allowed to manage the corp buyback requests / contracts. 
#### Admin
This permission gives access to the admin section. Here you can adjust some general plugin settings and configure the buyback item settings.

## Usage

User:
1. Copy & Paste your items you want to sell into the form under request.
2. If everything is fine create the contract in EVE with the details shown on the right
3. Click on Confirm. Done. You will be redirected to the "My Buyback" showing you created buyback.
4. Contract-Manager: Under "Contracts" you can see all created contracts and are able to delete or finish them after you have compared them with the ingame contract of the corp mate.The random generated ID will help you to find contracts faster.

> :warning: The buyback will only be saved with the click on "Confirm". Created contracts in EVE can not be seen by the plugin.

> :warning: Each item price is cached and only refreshed by default every hour. You can change the cache time over the admin section. Please **do not** set this value too low because this would spam the chosen price provider api and your server could get banned for a while.

## Discord Notifications
You are able to receive on every new buyback request discord notification over a discord webhook url directly into a discord channel. By default, the discord notification are turned off. You have to provide a valid discord webhook url over the admin settings page first.

How can I get my channel webhook url?
[Webhook Url Guide](https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks)

Example:

![discord](https://raw.githubusercontent.com/depotism/seat-buyback/refs/heads/master/img/discord_notification.png)

## Quick Installation Guide:
I can also recommend reading through the [official seat documentation](https://eveseat.github.io/docs/community_packages/).
### Install
Switch to your seat installation directory ( default : /var/www/seat)

```shell
sudo -H -u www-data bash -c 'php artisan down'
sudo -H -u www-data bash -c 'composer require depotism/seat-buyback'
sudo -H -u www-data bash -c 'php artisan vendor:publish --force --all'
sudo -H -u www-data bash -c 'php artisan migrate'
sudo -H -u www-data bash -c 'php artisan seat:cache:clear'
sudo -H -u www-data bash -c 'php artisan config:cache'
sudo -H -u www-data bash -c 'php artisan route:cache'
sudo -H -u www-data bash -c 'php artisan up'
```
*Note that `www-data` is the default ubuntu webserver user. If you are running on a different distribution please adjust the user.
### Docker Install
Open your .env file and edit the SEAT_PLUGINS variable to include the package.
```
# SeAT Plugins
SEAT_PLUGINS=depotism/seat-buyback
```
After adding the plugin to your .env file run:
```
docker-compose up -d
```
The plugin should be installed after docker has finished booting.

## Update
To update the plugin to the newest version you can follow the same installation steps but change the composer command to:
```shell
sudo -H -u www-data bash -c 'composer update depotism/seat-buyback'
```

## Donations
Donations are always welcome, although not required. If you end up using this module a lot, I'd appreciate a donation.
You can give ISK or contract PLEX and Ships to `depotism`.



