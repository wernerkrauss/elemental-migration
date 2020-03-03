# Elemental Migration Task [![Build Status](https://travis-ci.org/wernerkrauss/elemental-migration.svg?branch=master)](https://travis-ci.org/wernerkrauss/elemental-migration)
A migration tool for Silverstripe's Elemental V1 (SS3) to V2 (SS4) and above. Use at your own risk!

## Installation

I suggest to add this task as a development requirement; 

```
composer require --dev wernerkrauss/elemental-migration
```

## Run the task
1) Backup your database
1) Research changes in Elemental and its elements
1) Rename element tables that have changed. This might be more secure than relying on the built in copy mechanism.
1) run the task, e.g. by calling `/dev/tasks/Netwerkstatt-ElementalMigration-Task-ElementalMigration`
1) enjoy!

## What it does
Elemental 1 (Silverstripe CMS 3) was based on the Widget module. From Elemental 2 (Silverstripe CMS 4) it didn't have this dependency. This also meant some huge changes in the database structure.

The migration task creates ElementAreas according to the old WidgetAreas, syncs some important data from Widget to the Element table, updates ClassNames and can copy over data from tables that have been renamed. It's tested for [Elemental](https://github.com/dnadesign/silverstripe-elemental) and [Elemental Virtual](https://github.com/dnadesign/silverstripe-elemental-virtual)

## What it does not
It does not migrate [Elemental Virtual List](https://github.com/dnadesign/silverstripe-elemental-list).

## Configuration
For updating the ClassName property the task relies on `SilverStripe\ORM\DatabaseAdmin.classname_value_remapping` config. 

If the table name has changed you can either rename the table (incl. Versions and Live table) before running dev/build, or you can configure the migration task to copy over the values to the renamed table by adding the old => new map to `Netwerkstatt\ElementalMigration\Task\ElementalMigration.data_migration`. This mechanism does not check if every field from the old table exists on the new table and might break easily.

See [legacy.yml](_config/legacy.yml) for examples.

## Acknowledgments
A big thank you to [Andy Adiwidjaja](http://www.adiwidjaja.com/) for asking me to write this migration task and giving permission to open source the module.