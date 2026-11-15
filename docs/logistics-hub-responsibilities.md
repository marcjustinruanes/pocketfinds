# Logistics and hub responsibilities

The website separates company approval and oversight from local rider dispatch.

| Function | Responsible account | Scope |
| --- | --- | --- |
| Review seller pickup requests | Logistics Admin | Own company; existing shipping-payment checks apply |
| Offer pickup work | Origin Hub Staff | Own operational hub; eligible origin-hub rider |
| Offer final delivery work | Destination Hub Staff | Own operational hub; eligible destination-hub rider |
| Request pickup/delivery rider reassignment | Responsible Hub Staff | Origin for pickup, destination for delivery |
| Confirm pre-collection possession for pickup reassignment | Seller | Own parcel; no physical handover recorded |
| Confirm pre-release possession for delivery reassignment | Destination Hub Staff | Actual physical no-release confirmation |
| Receive and sort picked-up parcels | Origin Hub Staff | Actual physical receipt at own hub |
| Receive inter-hub transfers | Receiving Hub Staff | Actual receipt at destination hub |
| Receive recovered custody | Hub Staff | Own hub; existing evidence requirements apply |
| Request an outgoing hub transfer | Sending Hub Staff | Parcels at own hub |
| Approve and dispatch inter-hub transfers | Logistics Admin | Company coordination across hubs |
| Rider acceptance, pickup handover and delivery evidence | Assigned Rider | Existing seller and recipient confirmations remain required |
| Staffing, carrier rates, accounts and payouts | Logistics Admin | Company administration and finance |
| Final platform approval for hub staff | Platform Admin | Requires company interview confirmation, valid documents and an operational hub |
| Company monitoring and assignment history | Logistics Admin | Oversight; cannot send ordinary rider offers or initiate reassignment |

Hub Operations now links directly to Pickup riders. The same authorization applies
inside the offer and reassignment services, so hiding admin controls is not the
only protection. Existing automatic offers and rider acceptance are preserved.

Validation: focused permission, origin/destination scope and page checks pass.
The broader operational recovery suite also reports failures involving compensation,
recovery UI and eligibility; those workflows are not certified by this change.
