<dialog id="hubsModal" class="hubs-modal-backdrop" aria-labelledby="hubsModalTitle" aria-describedby="hubsModalSub" onclick="if(event.target===this){const r=this.getBoundingClientRect();if(event.clientX&lt;r.left||event.clientX&gt;r.right||event.clientY&lt;r.top||event.clientY&gt;r.bottom)closeHubModal()}">
                    <div class="hubs-modal-dialog">
                        <div class="hubs-modal-header">
                            <div>
                                <h3 class="hubs-modal-title" id="hubsModalTitle">Available hubs</h3>
                                <p class="hubs-modal-sub" id="hubsModalSub">Browse and select an available hub in your province</p>
                            </div>
                            <button type="button" class="hubs-modal-close" onclick="closeHubModal()" aria-label="Close">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="hubs-modal-search" id="hubsModalSearchBox">
                            <div class="hubs-search-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="search" class="hubs-search-input" id="hubsSearchInput" aria-label="Search hubs" placeholder="Search city or municipality" oninput="filterHubsModal(this.value)">
                            </div>
                        </div>
                        <div class="hubs-modal-body" id="hubsModalList"></div>
                        <div class="hubs-modal-footer">
                            <div class="hubs-footer-left">
                                <span>Selected:</span>
                                <strong class="hubs-selection-val" id="hubsModalSelectedText">None</strong>
                            </div>
                            <div class="hubs-footer-actions">
                                <button type="button" class="btn-hubs-cancel" onclick="closeHubModal()">Cancel</button>
                                <button type="button" class="btn-hubs-confirm" id="btnConfirmHubSelection" onclick="confirmHubModalSelection()">Use this hub</button>
                            </div>
                        </div>
                    </div>
                </dialog>
