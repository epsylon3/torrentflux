#######################################################################
#                                                                     #
#  BlueFlux theme for Torrentflux (http://www.torrentflux.com)        #
#                                                                     #
#  By: Micke (RaWeN @ torrentflux forums)                             #
#  Contact: ICQ 32133138  HTTP www.riskaka.com                        #
#                                                                     #
#######################################################################


Installation (Optional steps within [])
----------------------------------------
1. Unzip and upload the BlueFlux folder to /*your_torrentflux_directory*/themes/

[ 2. BACKUP YOUR CURRENT FILES BEFORE PROCEEDING!  ]
[ 3. Replace the icons in /*your_torrentflux_directory/images with the ones in BlueFlux/main-icons/ ]
[ 4. Edit the files below to get the final touches. ]

5. Enjoy your new theme =)



Changed files:
--------------

# downloaddetails.php

Original: <table width="100%" cellpadding="0" cellspacing="0" border="0">
New: <table width="100%" cellpadding="0" cellspacing="0" border="0" class="detailbar">

* Also the heights of the to <td> below this line is changed from 13 to 12


# functions.php

Original: <table width="100%" border="0" cellpadding="0" cellspacing="0">
New: <table width="100%" border="0" cellpadding="0" cellspacing="0" class="detailbar">



Changelog (not much but hey, why not!): 
---------------------------------------
20051120 - 1.0: First official release
		
		Changed since last time
		* More icons
		* Added instructions on how to change files
		* Added .detailbar class in style.css
.

20051118 - 0.1: The all new blueflux theme.