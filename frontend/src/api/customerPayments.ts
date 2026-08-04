import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
import type { CreateCustomerPaymentInput, CustomerPayment } from '@/types/customerPayment'

export async function createCustomerPayment(input: CreateCustomerPaymentInput): Promise<CustomerPayment> {
  const { data } = await api.post<ApiResponse<CustomerPayment>>('/customer-payments', input)
  return data.data
}
