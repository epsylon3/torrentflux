
package Net::BitTorrent::File;
use strict;
use warnings;
use Convert::Bencode qw(:all);
use Digest::SHA1 qw(sha1);

BEGIN {
	use Exporter ();
	use vars qw ($VERSION @ISA @EXPORT @EXPORT_OK %EXPORT_TAGS);
	$VERSION     = 1.02;
	@ISA         = qw (Exporter);
	#Give a hoot don't pollute, do not export more than needed by default
	@EXPORT      = qw ();
	@EXPORT_OK   = qw ();
	%EXPORT_TAGS = ();
}

=head1 NAME

Net::BitTorrent::File - Object for manipulating .torrent files

=head1 SYNOPSIS

  use Net::BitTorrent::File

  # Empty N::BT::File object, ready to be filled with info
  my $torrent = new Net::BitTorrent::File;

  # Or, create one from a existing .torrent file
  my $fromfile = new Net::BitTorrent::File ('somefile.torrent');

  $torrent->name('Some_File_to_distribute.tar.gz');
  $torrent->announce('http://address.of.tracker:6695');
  # etc.

  print $torrent->name()."\n";
  # would print "Some_File_to_distribute.tar.gz" in this case.

=head1 DESCRIPTION

This module handles loading and saveing of .torrent files as well as
providing a convenient way to store torrent file info in memory.
Most users of the module will most likely just call the new method
with the name of a existing torrent file and use the data from that.

=head1 USAGE

The same method is used for setting and retrieving a value, and the
methods have the same name as the key in the torrent file, such as C<name()>,
and C<announce()>. If the method is called with no arguments or a undefined
value, then the current value is returned, otherwise its set to the value
passed in.

There are two methods for generating info based on torrent data, but not
stored within the torrent itself. These are C<gen_info_hash()> and C<gen_pieces_array()>.
You can use the methods C<info_hash()> and C<pieces_array()> to return the calculated
values after calling there respective C<gen_X()> methods.

C<info_hash()> returns the SHA1 hash of the info portion of the torrent which is
used in the bittorrent protocol.

C<pieces_array()> returns a array ref of the pieces field of the torrent split
into the individual 20 byte SHA1 hashes. For further details on what exactly
these are used for, see the docs for the bittorrent protocol mentioned in
the SEE ALSO section.

=head2 Methods

=over 4

=item * new( [$filename] )

Creates a new Net::BitTorrent::File object, and if a filename is
supplied will call the load method with that filename.

=item * load( $filename )

Loads the file passed into it and generates the C<info_hash> and C<pieces_array>
propertys.

=item * save( $filename )

Saves the torrent to I<$filename>. Note that C<info_hash> and C<pieces_array> are
not saved to the torrent file and must be regenerated when the torrent is
loaded (but the C<load()> method does this for you anyway).

=item * info_hash( [$new_value] )

When called with no arguments returns the I<info_hash> value, otherwise it sets
it to the value in I<$new_value>. Note: Its very unlikely anyone will be using
to set the value of I<info_hash>, rather you should populate all the info
fields then call C<gen_info_hash()> to set this property.

=item * gen_info_hash( )

Calculates the SHA1 hash of the torrents I<info> field and stores this in the
I<info_hash> property which can be retrieved using the C<info_hash()> method.

=item * pieces_array( [$new_array] )

When called with no arguments returns a array ref whose values are the
SHA1 hashes contained in the I<pieces> property. To set this value, do not use
this method, rather use the C<gen_pieces_array()> method, after setting the
I<pieces> property.

=item * gen_pieces_array( )

Divides the I<pieces> property into its component 20 byte SHA1 hashes, and
stores them as a array ref in the I<pieces_array> property.

=item * name( [$value] )

When called with no arguments returns the I<name> propertys current value, else
it sets it to I<$value>. If this value is changed, the I<info_hash> property needs
to be regenerated.

=item * announce( [$value] )

When called with no arguments returns the I<announce> propertys current value, else
it sets it to I<$value>.

=item * piece_length( [$value] )

When called with no arguments returns the I<piece_length> propertys current value, else
it sets it to I<$value>. If this value is changed, the I<info_hash> property needs
to be regenerated.

=item * length( [$value] )

When called with no arguments returns the I<length> propertys current value, else
it sets it to I<$value>. If this value is changed, the I<info_hash> property needs
to be regenerated.

=item * pieces( [$value] )

When called with no arguments returns the I<pieces> propertys current value, else
it sets it to I<$value>. If this value is changed, the I<info_hash> and I<pieces_array>
propertys need to be regenerated.

=item * files( [$value] )

When called with no arguments returns the I<files> propertys current value, else
it sets it to I<$value>. I<$value> should be a array ref filled with hash refs
containing the keys I<path> and I<length>. If this value is changed, the I<info_hash>
property needs to be regenerated.

=item * info( [$value] )

When called with no arguments returns the I<info> propertys current value, else
it sets it to I<$value>. I<$value> should be a hash ref containing the keys
I<files>, I<pieces>, I<length>, I<piece_length>, and I<name>. If this value is changed, the
I<info_hash> property needs to be regenerated.

=back

=head1 BUGS

None that I know of yet.

=head1 SUPPORT

Any bugs/suggestions/problems, feel free to send me a e-mail, I'm usually
glad to help, and enjoy hearing from people using my code. My e-mail is
listed in the AUTHOR section.

=head1 AUTHOR

	R. Kyle Murphy
	orclev@rejectedmaterial.com

=head1 COPYRIGHT

This program is free software; you can redistribute
it and/or modify it under the same terms as Perl itself.

The full text of the license can be found in the
LICENSE file included with this module.


=head1 SEE ALSO

L<Convert::Bencode>, http://bitconjurer.org/BitTorrent/protocol.html

=cut

sub new
{
	my ($class, $file) = @_;

	my $self = bless ({}, ref ($class) || $class);

	if(defined($file)) {
		$self->load($file);
	}

	return ($self);
}

sub name {
	my $self = shift;
	my $name = shift;
	if(defined($name)) {
		$self->{'data'}->{'info'}->{'name'} = $name;
	}
	return $self->{'data'}->{'info'}->{'name'};
}

sub announce {
	my $self = shift;
	my $announce = shift;
	if(defined($announce)) {
		$self->{'data'}->{'announce'} = $announce;
	}
	return $self->{'data'}->{'announce'};
}

sub piece_length {
	my $self = shift;
	my $len = shift;
	if(defined($len)) {
		$self->{'data'}->{'info'}->{'piece_length'} = $len;
	}
	return $self->{'data'}->{'info'}->{'piece_length'};
}

sub length {
	my $self = shift;
	my $len = shift;
	if(defined($len)) {
		$self->{'data'}->{'info'}->{'length'} = $len;
	}
	return $self->{'data'}->{'info'}->{'length'};
}

sub pieces {
	my $self = shift;
	my $pieces = shift;
	if(defined($pieces)) {
		$self->{'data'}->{'info'}->{'pieces'} = $pieces;
	}
	return $self->{'data'}->{'info'}->{'pieces'};
}

sub pieces_array {
	my $self = shift;
	my $array = shift;
	if(defined($array)) {
		$self->{'pieces_array'} = $array;
	}
	return $self->{'pieces_array'}; 
}

sub gen_pieces_array {
	my $self = shift;
	
	if(defined($self->pieces())) {
		my @pieces = $self->pieces() =~ /.{20}/sg;
		$self->pieces_array(\@pieces);
	}
}

sub files {
	my $self = shift;
	my $files = shift;
	if(defined($files)) {
		$self->{'data'}->{'info'}->{'files'} = $files;
	}
	return $self->{'data'}->{'info'}->{'files'};
}

sub info {
	my $self = shift;
	my $info = shift;
	if(defined($info)) {
		$self->{'data'}->{'info'} = $info;
	}
	return $self->{'data'}->{'info'};
}

sub info_hash {
	my $self = shift;
	my $hash = shift;
	if(defined($hash)) {
		$self->{'info_hash'} = $hash;
	}
	return $self->{'info_hash'};
}

sub gen_info_hash {
	my ($self) = shift;
        $self->info_hash(sha1(bencode($self->info())));
}

sub load {
	my ($self, $file) = @_;
	my $buff = '';

	open(FILE, '< '.$file);
	local $/;
	$buff = <FILE>;
	close(FILE);
	my $root = bdecode($buff);
	$self->{'data'} = $root;
	$self->gen_info_hash;
	$self->gen_pieces_array;
}

sub save {
        my ($self, $file) = @_;

        my $data = bencode($self->{'data'});
        open(FILE, '> '.$file);
        print FILE $data;
        close(FILE);
}

1;
__END__

