<?php

Schedule::command('queue:work --stop-when-empty')->everyMinute();
