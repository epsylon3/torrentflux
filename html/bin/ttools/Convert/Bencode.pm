#!/usr/bin/perl -w
package Convert::Bencode;

=head1 NAME

Convert::Bencode - Functions for converting to/from bencoded strings

=head1 SYNOPSIS

  use Convert::Bencode qw(bencode bdecode);

  my $string = "d4:ainti12345e3:key5:value4:type4:teste";
  my $hashref = bdecode($string);

  foreach my $key (keys(%{$hashref})) {
      print "Key: $key, Value: ${$hashref}{$key}\n";
  }

  my $encoded_string = bencode($hashref);
  print $encoded_string."\n";

=head1 DESCRIPTION

This module provides two functions, C<bencode> and C<bdecode>, which
encode and decode bencoded strings respectivly.

=head2 Encoding

C<bencode()> expects to be passed a single value, which is either a scalar, 
a arrary ref, or a hash ref, and it returns a scalar containing the bencoded 
representation of the data structure it was passed. If the value passed was 
a scalar, it returns either a bencoded string, or a bencoded integer (floating 
points are not implemented, and would be returned as a string rather than a 
integer). If the value was a array ref, it returns a bencoded list, with all 
the values of that array also bencoded recursivly. If the value was a hash ref,
it returns a bencoded dictionary (which for all intents and purposes can be 
thought of as a synonym for hash) containing the recursivly bencoded key and 
value pairs of the hash.

=head2 Decoding

C<bdecode()> expects to be passed a single scalar containing the bencoded string
to be decoded. Its return value will be either a hash ref, a array ref, or a
scalar, depending on whether the outer most element of the bencoded string
was a dictionary, list, or a string/integer respectivly.

=head1 SEE ALSO

The description of bencode is part of the bittorrent protocol specification
which can be found at http://bitconjurer.org/BitTorrent/protocol.html

=head1 BUGS

No error detection of bencoded data. Damaged input will most likely cause very bad things to happen, up to and including causeing the bdecode function to recurse infintly.

=head1 AUTHOR & COPYRIGHT

Created by R. Kyle Murphy <orclev@rejectedmaterial.com>, aka Orclev.

Copyright 2003 R. Kyle Murphy. All rights reserved. Convert::Bencode
is free software; you may redistribute it and/or modify it under the
same terms as Perl itself.

=cut

use strict;
use warnings;
use bytes;

BEGIN {
	use Exporter ();
	our ($VERSION, @ISA, @EXPORT, @EXPORT_OK, @EXPORT_FAIL, %EXPORT_TAGS);

	$VERSION 	= 1.03;
	@ISA		= qw(Exporter);
	@EXPORT_OK	= qw(&bencode &bdecode);
	@EXPORT_FAIL	= qw(&_dechunk);
	%EXPORT_TAGS	= (all => [qw(&bencode &bdecode)]);
}
our @EXPORT_OK;

END { }

sub bencode {
	no locale;
	my $item = shift;
	my $line = '';
	if(ref($item) eq 'HASH') {
		$line = 'd';
		foreach my $key (sort(keys %{$item})) {
			$line .= bencode($key);
			$line .= bencode(${$item}{$key});
		}
		$line .= 'e';
		return $line;
	}
	if(ref($item) eq 'ARRAY') {
		$line = 'l';
		foreach my $l (@{$item}) {
			$line .= bencode($l);
		}
		$line .= 'e';
		return $line;
	}
	if($item =~ /^\d+$/) {
		$line = 'i';
		$line .= $item;
		$line .= 'e';
		return $line;
	}
	$line = length($item).":";
	$line .= $item;
	return $line;
}

sub bdecode {
	my $string = shift;
	my @chunks = split(//, $string);
	my $root = _dechunk(\@chunks);
	return $root;
}

sub _dechunk {
	my $chunks = shift;

	my $item = shift(@{$chunks});
	if($item eq 'd') {
		$item = shift(@{$chunks});
		my %hash;
		while($item ne 'e') {
			unshift(@{$chunks}, $item);
			my $key = _dechunk($chunks);
			$hash{$key} = _dechunk($chunks);
			$item = shift(@{$chunks});
		}
		return \%hash;
	}
	if($item eq 'l') {
		$item = shift(@{$chunks});
		my @list;
		while($item ne 'e') {
			unshift(@{$chunks}, $item);
			push(@list, _dechunk($chunks));
			$item = shift(@{$chunks});
		}
		return \@list;
	}
	if($item eq 'i') {
		my $num;
		$item = shift(@{$chunks});
		while($item ne 'e') {
			$num .= $item;
			$item = shift(@{$chunks});
		}
		return $num;
	}
	if($item =~ /\d/) {
		my $num;
		while($item =~ /\d/) {
			$num .= $item;
			$item = shift(@{$chunks});
		}
		my $line = '';
		for(1 .. $num) {
			$line .= shift(@{$chunks});
		}
		return $line;
	}
	return $chunks;
}

1;
