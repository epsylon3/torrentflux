################################################################################
# $Id$
# $Date$
# $Revision$
################################################################################
#                                                                              #
# LICENSE                                                                      #
#                                                                              #
# This program is free software; you can redistribute it and/or                #
# modify it under the terms of the GNU General Public License (GPL)            #
# as published by the Free Software Foundation; either version 2               #
# of the License, or (at your option) any later version.                       #
#                                                                              #
# This program is distributed in the hope that it will be useful,              #
# but WITHOUT ANY WARRANTY; without even the implied warranty of               #
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the                 #
# GNU General Public License for more details.                                 #
#                                                                              #
# To read the license please visit http://www.gnu.org/copyleft/gpl.html        #
#                                                                              #
#                                                                              #
################################################################################
package FluxCommon;
use Exporter;
@ISA = ('Exporter');
@EXPORT_OK = qw(
				getVersion
				transferIsRunning
				getTimeStamp
				niceTimeString
				);
################################################################################

################################################################################
# fields                                                                       #
################################################################################

# version in a var
my $VERSION = do {
	my @r = (q$Revision$ =~ /\d+/g); sprintf "%d"."%02d" x $#r, @r };

################################################################################
# subs                                                                         #
################################################################################

#------------------------------------------------------------------------------#
# Sub: transferIsRunning                                                       #
# Arguments: transfer                                                          #
# Return: 0|1                                                                  #
#------------------------------------------------------------------------------#
sub transferIsRunning {
	my $name = shift;
	my $qstring = "ps x -o pid='' -o ppid='' -o command='' -ww 2> /dev/null";
	my $pcount = 0;
	foreach my $line (grep(/$name/, qx($qstring))) {
		$pcount++;
	}
	if ($pcount > 1) {
		return 1;
	}
	return 0;
}

#------------------------------------------------------------------------------#
# Sub: getTimeStamp                                                            #
# Arguments:                                                                   #
# Return: string                                                               #
#------------------------------------------------------------------------------#
sub getTimeStamp {
	my ($sec, $min, $hour, $mday, $mon, $year, $wday, $yday, $isdst)
		= localtime(time);
	return sprintf("[%4d/%02d/%02d - %02d:%02d:%02d]",
						$year + 1900, $mon + 1, $mday,
						$hour, $min, $sec
	);
}

#------------------------------------------------------------------------------#
# Sub: niceTimeString                                                          #
# Arguments: start-time                                                        #
# Return: nice Time String                                                     #
#------------------------------------------------------------------------------#
sub niceTimeString {
	my $startTime = shift;
	my ($dura, $duration, $days, $hours, $mins, $secs, $rest);
	$dura = ((time) - $startTime);
	$rest = $dura;
	$days = $hours = $mins = $secs = 0;
	$duration = "";
	if ($dura >= (24 * 60 * 60)) { # days
		$days = int((($rest / 60) / 60) / 24);
		$duration .= $days."d ";
		$rest = ($dura - ($days * 60 * 60 * 24));
	}
	if ($dura >= (60 * 60)) { # hours
		$hours = int(($rest / 60) / 60);
		$duration .= $hours."h ";
		$rest = ($dura - ($hours * 60 * 60) - ($days * 60 * 60 * 24));
	}
	if ($rest >= 60) { # mins
		$mins = int($rest / 60);
		$duration .= $mins."m ";
		$rest = ($dura - ($mins * 60) - ($hours * 60 * 60) - ($days * 60 * 60 * 24));
	}
	if ($rest > 0) { # secs
		$duration .= $rest."s";
	}
	return $duration;
}

#------------------------------------------------------------------------------#
# Sub: getVersion                                                              #
# Arguments: null                                                              #
# Returns: VERSION                                                             #
#------------------------------------------------------------------------------#
sub getVersion {
	return $VERSION;
}

################################################################################
# make perl happy                                                              #
################################################################################
1;
