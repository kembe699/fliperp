import { create } from 'zustand'

interface UiState {
  selectedBranchId: number | null
  setSelectedBranchId: (branchId: number | null) => void
}

/** Not persisted — resets to "all branches" (or the user's own branch) each session. */
export const useUiStore = create<UiState>((set) => ({
  selectedBranchId: null,
  setSelectedBranchId: (branchId) => set({ selectedBranchId: branchId }),
}))
