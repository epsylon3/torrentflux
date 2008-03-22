# author: David Harrison

# In an attempt for us to have consistent notions of torrent health
# across search and our various tools, I thought it made sense
# to define health stats in BTL.


def reciprocity( downloaders ):
    # measure of the effectiveness of tit-for-tat.
    # This is heuristic for lack of a better model.
    low_thresh = 5    # traditional tit-for-tat algo makes no sense when there are <= 5.
    high_thresh = 50
    # '+1' includes the local host if it were to join the swarm.
    # This retains consistency with the use of 'downloaders+1' used in the
    # download_rate_health metric.
    if downloaders + 1 < low_thresh:
        gamma = 0.
    elif downloaders + 1 < high_thresh:
        gamma  = (downloaders + 1. - low_thresh)/(high_thresh - low_thresh)
    else:
        gamma = 1.
    return gamma

    
def download_rate_health( seeders, downloaders, nats = 0 ):
    """
     This health metric preserves order based on expected download rates.
     It is not necessarily proportional to expected download rate.

     Let downrate_i = download rate from torrent i.

     Let uprate_i = upload rate to torrent i.

     Let downloaders_i = number of downloadeers in torrent i.

     Let seeders_i = number of seeders in torrent i.

       E[downrate_i] = E[downrate from tit-for-tat_i] + E[downrate from seeders_i]  (1)

     Assumption 1: downrate from tit-for-tat is related to uprate by a function gamma_i.
     Let reciprocity gamma_i = effectiveness of tit-for-tat.

       E[downrate from tit-for-tat_i] = gamma_i * uprate_i                          (2)

     where uprate_i is the local uplink capacity that can be committed to torrent i.
     Assuming uprate is invariant across the torrents that I might join, (2) becomes

       E[downrate from tit-for-tat_i] = gamma_i * uprate                            (3)

     Assumption 2: my expected downrate for any torrent i is equal to the
     average download rate due to seeders across the torrent swarm for i.  Due to
     conservation of bits.  The sum download rate due to seeders must equal
     the sum upload rate from seeders.  Thus
     
                                       Sum_k uprate from seeder k
       E[downrate from seeders_i] =  -----------------------------                 
                                             downloaders_i

                                     seeders_i * E[uprate from seeder]
                                     --------------------------------                (4)
                                             downloaders_i

     Assumption 3: E[update from seeder] = uprate
     This expectation might reasonably hold when the local user is a typical
     residential customer and all seeders are also residential customers.
     This is likely to be far off when their are many infrastructure seeds.

       E[downrate from seeders_i] = seeders_i * uprate / downloaders_i

     and thus combining with (1) and (3) yields

       E[downrate_i] = gamma_i * uprate + seeders_i * uprate / downloaders_i         (5)

     Our objective is a health metric H such that

       H_i > H_j  --->  E[downrate_i] > E[downrate_j]  for all i,j

     Because uprate >= 0,
     
       E[downrate_i]     E[downrate_j]          
       ------------   >  -------------   ---> E[downrate_i] > E[downrate_j]  for all i,j
         uprate             uprate

     We thus define rate health metric Hr_i

                          seeders_i
       Hr_i = gamma_i + -------------                                                 (6)
                        downloaders_i

     To take into account the local user in the health metric, assuming the user is a
     potential downloader, this becomes

                          seeders_i
       Hr_i = gamma_i + -----------------                                             (7)
                        downloaders_i + 1

     The +1 also eliminates the potential divide by zero error that would arise in
     (6) were downloaders_i = 0.

     If gamma_i = gamma_j for all i,j then we can simplify (7) while preserving order
     by subtracting out gamma_i.  This leads to
     
                 seeders_i
       Hr_i = -----------------                                                       (8)
              downloaders_i + 1

     From experience, we know that the tit-for-tat algorithm works poorly when there
     are few downloaders in a swarm.  At the extreme when there are less than 5,
     the algorithm does nothing: the traditional tit-for-tat algorithm unchokes
     the best 4 fastest plus 1 randomly chosen 'optimistic' unchoke.  Reciprocity
     should be some function of the number of downloaders in the swarm.  For lack
     of a better study, I suggest the following

                       downloaders_i +1          
                 /     ----------------     for downloaders_i < thresh
                 |          thresh
       gamma_i  <
                 |            1             otherwise
                 \

      Thus the effectivness of reciprocity increases linearly until it hits a threshold
      and reciprocation becomes perfect above that threshold.

      @param seeds: number of non-natted peers that have the entire file
          and are still in the torrent swarm.
      @param downloaders: number of non-natted downloaders in the swarm
      @param nats: number of natted downloaders in the swarm. 
    """
    assert downloaders >= 0
    assert seeders >= 0
    gamma = reciprocity(downloaders)
    Hr = health = gamma + seeders / (downloaders + nats + 1.)
    return health

def download_time_health( seeders, downloaders, nats, filesize ):
    """
      Health metric that perserves order based on download times.
      Smaller is better.  (Confusing.  I couldn't decide whether
      "smaller is better" or "bigger is better" is more appropriate.)

      Let downtime_i = download time for the entire file from torrent i.
      Thus
          Ht_i > Ht_j --> E[downtime_i] > E[downtime_j]                      (9)

                          filesize_i
        E[downtime_i] =  ------------
                         E[downrate_i]

        
      Using the same definitions ofr downrate_i as used in derive the
      health metric for download_rate_health, (9) becomes

                                        filesize_i
        E[downtime_i] = ------------------------------------------------------
                        gamma_i * uprate_i + seeders_i * uprate_i / downloaders_i


      Because uprate_i is positive,
                                               
        E[downtime_i] > E[downtime_j]  <----

                             filesize_i                             filesize_j                
                    ------------------------------      > ------------------------------     
                    gamma_i + seeders_i / downloaders_i   gamma_i + seeders_j / downloaders_j

      Including +1 and using the same gamma as used from download_time_health,
      we define

                          filesize_i                
         Ht_i = --------------------------------------     
                gamma_i + seeders_i / (downloaders_i+1)

      @param seeds: number of non-natted peers that have the entire file
          and are still in the torrent swarm.
      @param downloaders: number of non-natted downloaders in the swarm.
      @param nats: number of natted downloaders in the swarm. 
    """
    assert downloaders >= 0
    assert seeders >= 0
    assert filesize >= 0
    gamma = reciprocity(downloaders)
    Hr = gamma + seeders / (downloaders+nats+1)
    Ht = filesize / Hr
    return Ht
                                        
