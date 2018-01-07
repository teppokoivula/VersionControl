Version Control module for ProcessWire
======================================

Version Control Module For ProcessWire CMS/CMF.
Copyright (c) 2013-2018 Teppo Koivula

This module uses hooks provided by ProcessWire to catch page edits and store
revision data in a series of custom database tables, so that it can be later
retrieved, reviewed, and restored.

Note that the module provides settings which you can use to define the specific
fieldtypes, fields, and templates to enable version control for. Out of the box
the module won't track anything at all.

While editing a page with version control enabled, a revision toggle (icon link
that opens a list of previous revisions) is placed next to fields for which the
module is enabled for. From this list you can select a specific revision to
which you would like to rollback the value of the field.

In addition the version history can be viewed on Page level via the History tab
automaticaly added to Page Editor for templates that have tracking enabled.

## Supported fieldtypes and inputfields

All native ProcessWire fieldtypes and inputfields, apart from those that either
don't directly store values at all (repeaters fields, fieldsets), or only store
hashed values (password), should be supported:

  * Email
  * Datetime
  * Text (regular and multi-language)
  * Textarea (regular and multi-language)
  * Page Title (regular and multi-language)
  * Checkbox
  * Integer
  * Float
  * URL
  * Page
  * Module
  * File
  * Image
  * Selector
  * Options
  
Note that if a specific fieldtype isn't listed here, it doesn't necessarily mean
that it won't work with this module – it may not have been tested yet.

You can enable support for any installed fieldtype locally via the "Compatible
fieldtypes" setting, which can be found under "Advanced Settings" from Version
Control module configuration.

Following inputfields are confirmed to be supported:

  * TinyMCE
  * CKEditor (regular and inline mode)
  * Text (+ other inputfields using `<input>` HTML element, such as Email)
  * Textarea (+ other inputfields using regular `<textarea>` HTML element)
  * Select
  * File
  * Image
  * Selector

## Requirements

Please note that this module is mainly developed and tested under the latest
stable version of ProcessWire. This also means that no 2.x versions of will
be officially supported. In case you're using ProcessWire 2.x > 2.4.1, you
should check out the legacy branch of Version Control:

https://github.com/teppokoivula/VersionControl/tree/master

For ProcessWire 2.x versions before 2.4.1 the Version Control for Text Fields
module provides similar features, but is limited to text based fields only:

https://github.com/teppokoivula/VersionControlForTextFields

## Getting started

Copy (or clone with git) VersionControl folder to /site/modules/, go to Admin >
Modules, hit "Check for new modules" and install Version Control. Supporting
modules Process Version Control and Page Snapshot are installed automatically.

After installing this module you need to configure it. Navigate to Admin >
Modules > Version control for module configuration settings.

## Diff Match and Patch

The Diff Match and Patch libraries offer robust algorithms to perform the
operations required for synchronizing plain text. In the scope of current
module, the JavaScript implementation of Diff Match and Patch is used to
render diff between different revisions of a field value.

Diff Match and Patch is copyright (c) 2006 Google Inc. and released under
the Apache License, Version 2.0. For more information about this library,
please visit http://code.google.com/p/google-diff-match-patch/.

## License

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

(See included LICENSE file for full license text.)