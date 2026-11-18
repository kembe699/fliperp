import { create } from 'zustand'

interface UiState {
  selectedBranchId: number | null
  setSelectedBranchId: (branchId: number | null) => void
  /** Phones and tablets: the main menu slides in over the page. */
  mobileNavOpen: boolean
  setMobileNavOpen: (open: boolean) => void
}

/** Not persisted — resets to "all branches" (or the user's own branch) each session. */
export const useUiStore = create<UiState>((set) => ({
  selectedBranchId: null,
  setSelectedBranchId: (branchId) => set({ selectedBranchId: branchId }),
  mobileNavOpen: false,
  setMobileNavOpen: (open) => set({ mobileNavOpen: open }),
}))
