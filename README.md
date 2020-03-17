# RGBeacon
[![](https://poggit.pmmp.io/shield.state/RGBeacon)](https://poggit.pmmp.io/p/RGBeacon)
[![HitCount](http://hits.dwyl.io/Xenophilicy/RGBeacon.svg)](http://hits.dwyl.io/Xenophilicy/RGBeacon)
[![Discord Chat](https://img.shields.io/discord/490677165289897995.svg)](https://discord.gg/hNVehXe)

# [![RGBeacon](https://file.xenoservers.net/Resources/GitHub-Resources/rgbeacon/rgbeacon.gif)]()

## Information
This plugin allows you to spawn in beacons that change colors according to what you throw in the plugin's config! You are able to change the color of the beacon, order of colors, and speed of the colors in the *config.yml* file! To spawn a new RGBeacon, simple execute the command `/beacon new` and the plugin will spawn a new beacon under your feet! Beacons can be removed with their corresponding ID that can be found when both creating the beacon and looking at the beacon list in the config file!

*You can find a video example on YouTube → https://www.youtube.com/watch?v=i5C-QT9w4W0*

[![Xenophilicy](https://img.youtube.com/vi/i5C-QT9w4W0/0.jpg)](https://www.youtube.com/watch?v=i5C-QT9w4W0)

Command List:
```
/beacon new → Creates a new beacon under the player
/beacon remove <id> → Removes a selected beacon from the world
/beacon pause <id> → Pause a beacon's color cycle
/beacon resume <id> → Resume a beacon's color cycle if it is currently paused
/beacon hide <id> → Temporarily disables a beacon's beam
/beacon show <id> → Enables a beacon's beam if it's currently hidden
/beacon set <id> <delay|colors> <setting> → Set the color order of a specified beacon
/beacon reload → Reload the plugin's config and reset all beacons
/beacon list <world/all> → Show beacons in the current world or all registered beacons on the server
```

The beacon colors that are implemented are:
- White
- Red
- Orange
- Pink
- Yellow
- Lime
- Green
- Light Blue
- Cyan
- Blue
- Magenta
- Purple
- Brown
- Gray
- Light Gray
- Black

### Known issues
The beacon beams will not be activated when chunks are unloaded and then reloaded
* An optional repeating task is supplied to update the beacon every X ticks in config
***

## RGBeacon Details
* **API:** 3.0.0+
* **Version:** 1.0.0
* **Basic Description:** Spawn multicolored beacons in your worlds!
* *Extensive yet easy-to-edit config.yml file*
* *Simple code for editing and debugging*
***

## Dependencies
**DevTools → https://github.com/pmmp/PocketMine-DevTools** *(If you are using the plugin folder method)*

## Credits
* [Xenophilicy](https://github.com/Xenophilicy/)
