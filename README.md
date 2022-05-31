# Opencast Event Object

This ILIAS plugin for Opencast is operated and developed collaboratively by a community. The University of Cologne acts as coordinative maintainer. The first version of this plugin will developed by the ELAN e.V.. Until then this repository will be a placeholder and considered beta.

Introduces a new object in which a single Opencast video can be selected.

# Usage

This plugin is meant to provide the ability to create an object in a course or a group that displays an opencast event, by choosing this event among all the available events in a form of a table list.
It relies on the main [ILIAS-Plugin Opencast](https://github.com/opencast-ilias/OpencastObject) to get the events, check accessibility and display the selected event using Paella player.

## Getting Started

### Requirements
* ILIAS 6.x / 7.x
* ILIAS-Plugin Opencast (version >= 4)

### Installation
Start at your ILIAS root directory
```bash
mkdir -p Customizing/global/plugins/Services/Repository/RepositoryObject/
cd Customizing/global/plugins/Services/Repository/RepositoryObject/
git clone https://github.com/opencast-ilias/OpencastEvent.git
```
As ILIAS administrator go to "Administration"->"Plugins" and install/activate the plugin.

### Creation
#### 1. Adding a repository object of Opencast Event to the course/group.
#### 2. Upon creating a list of available events are displayed to select from, which can be filtered after series, start date and text title.
#### 3. After creating, user can check through the properties and set the object online as well as applying the permissions and so on.
#### 4. When a user is landed on the content page, he/she could see the video player if the access right is permitted!
