<?php
/**
 * Component: Dashboard Facilities Tab
 * @var array $data Dashboard data.
 */
?>
<!-- 3. FACILITIES TAB -->
<div id="tab-facilities" class="tab-content d-none">
     <div class="row g-4">
         <div class="col-lg-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                  <h3 class="h5 fw-bold text-dark m-0">Available Facilities</h3>
                  <div class="position-relative" style="width: 150px;">
                      <input type="text" id="facility-dashboard-search" class="form-control form-control-sm rounded-pill shadow-none" placeholder="Search...">
                  </div>
              </div>
             <div class="d-flex flex-column gap-3">
                 <?php foreach ( $data['facilities'] as $f ) : ?>
                    <div class="facility-card bg-white rounded-3 border border-light p-3 shadow-sm d-flex justify-content-between align-items-center" data-search="<?php echo esc_attr(strtolower($f['name'])); ?>">
                        <div>
                            <div class="fw-medium text-dark"><?php echo esc_html( $f['name'] ); ?></div>
                            <div class="small text-secondary">₹<?php echo sgvx_in_fmt( $f['rate'] ?? 0 ); ?> / <?php echo esc_html( $f['rate_unit'] ?? 'hr' ); ?></div>
                        </div>
                        <button class="js-open-booking btn btn-sm btn-outline-success rounded-pill px-3 shadow-none" 
                                data-facility-id="<?php echo esc_attr($f['id']); ?>"
                                data-facility-name="<?php echo esc_attr($f['name']); ?>">Book</button>
                    </div>
                <?php endforeach; ?>
             </div>
         </div>
         <div class="col-lg-8">
             <div class="bg-white rounded-3 shadow-sm border border-light overflow-hidden">
                 <div class="px-4 py-3 border-bottom border-light bg-light d-flex justify-content-between align-items-center">
                     <span class="fw-semibold text-dark">My Bookings</span>
                     <div class="position-relative" style="width: 180px;">
                         <input type="text" id="booking-dashboard-search" class="form-control form-control-sm rounded-pill shadow-none" placeholder="Filter bookings...">
                     </div>
                 </div>
                 <div class="table-responsive">
                     <table class="table table-hover mb-0 align-middle">
                         <thead class="bg-light text-secondary text-uppercase small">
                             <tr><th class="ps-4">Facility</th><th>Date</th><th class="pe-4">Status</th></tr>
                         </thead>
                         <tbody>
                               <?php if ( empty( $data['my_bookings'] ) ) : ?>
									<tr><td colspan="3" class="text-center py-4 text-muted italic">No bookings found.</td></tr>
								<?php else : ?>
                                  <?php foreach ( $data['my_bookings'] as $b ) : 
										$fac_name = 'Unknown';
										foreach($data['facilities'] as $fa) { if($fa['id'] == $b['facility_id']) $fac_name = $fa['name']; }
									?>
                                  <tr class="booking-dash-row" data-search="<?php echo esc_attr(strtolower($fac_name)); ?>">
                                      <td class="ps-4 fw-medium text-dark"><?php echo esc_html( $fac_name ); ?></td>
                                      <td class="text-secondary"><?php echo date( 'M j, H:i', strtotime( $b['start_time'] ) ); ?></td>
                                      <td class="pe-4">
                                          <?php 
                                              $s_raw = strtolower($b['status'] ?? 'pending');
                                              $s_class = 'bg-success-subtle text-success';
                                              if ( $s_raw === 'pending' ) $s_class = 'bg-warning-subtle text-warning-emphasis';
                                              if ( $s_raw === 'rejected' ) $s_class = 'bg-danger-subtle text-danger';
                                              if ( $s_raw === 'cancelled' ) $s_class = 'bg-secondary-subtle text-secondary';
                                          ?>
                                          <span class="badge <?php echo $s_class; ?> rounded-pill text-uppercase fw-bold" style="font-size: 9px;"><?php echo esc_html( $b['status'] ); ?></span>
                                      </td>
                                  </tr>
                                  <?php endforeach; ?>
                             <?php endif; ?>
                         </tbody>
                     </table>
                 </div>
             </div>
         </div>
     </div>
</div>
