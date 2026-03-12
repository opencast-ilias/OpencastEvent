import il from 'ilias';
import $ from 'jquery';
import SettingsFormHandler from './Form/SettingsFormHandler.js';
import PlayerHandler from './Player/PlayerHandler.js';
import ListHandler from './List/ListHandler.js';

il.OpencastEvent = il.OpencastEvent || {};

il.OpencastEvent.form = new SettingsFormHandler($);

il.OpencastEvent.player = new PlayerHandler($);

il.OpencastEvent.list = new ListHandler($);
