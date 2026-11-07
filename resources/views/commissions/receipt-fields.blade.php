<label>Actual transfer amount PHP<input name="amount" type="number" min="0.01" step="0.01" required></label>
<label>GCash transaction reference<input name="reference" pattern="[A-Za-z0-9-]{6,64}" maxlength="64" required></label>
<label>Private payment proof (image or PDF, up to 5 MB)<input name="proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required></label>
<label><input type="checkbox" name="sent" value="1" required> I confirm this transfer was actually sent.</label>
