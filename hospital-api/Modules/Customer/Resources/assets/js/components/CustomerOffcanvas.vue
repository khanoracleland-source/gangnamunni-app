<template>
  <form @submit="formSubmit">
    <div :class="['offcanvas', offcanvasSideClass, 'offcanvas-w-40']" tabindex="-1" id="form-offcanvas" aria-labelledby="form-offcanvasLabel">
      <FormHeader :currentId="currentId" :editTitle="editTitle" :createTitle="createTitle"></FormHeader>
      <div class="offcanvas-body">
        <div class="row">
          <div class="col-md-6 create-service-image">
            <label for="" class="form-label">{{ $t('customer.lbl_profile_image') }}</label>
            <div class="image-upload-container text-center">
              <div class="clinic-image-preview d-flex justify-content-center align-items-center mb-2 mx-auto">
                <img :src="profile_image || image_url || defaultImage" alt="Profile Image" class="img-fluid object-fit-cover avatar-170 rounded-circle" />
              </div>
              <div class="d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-light" @click="triggerFileInput">
                  {{ $t('clinic.upload') }}
                </button>
                <!-- <button type="button" class="btn btn-danger" @click="removeImage">
                  {{ $t('messages.remove') }}
                </button> -->
              </div>
              <input type="file" 
                ref="profileInputRef" 
                class="form-control d-none" 
                id="file_url" 
                name="file_url" 
                @change="fileUpload" 
                accept=".jpeg, .jpg, .png, .gif" 
              />
              <div id="file-format-error" class="text-danger mt-1 d-none"></div>
              <span class="text-muted small">Only .jpeg, .jpg, .png files are allowed.</span>
            </div>
            <span class="text-danger">{{ errors.profile_image }}</span>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="" class="form-label">{{ $t('customer.lbl_first_name') }}<span class="text-danger">*</span></label>
              <InputField class="" :is-required="true" :label="$t('employee.lbl_first_name')" :placeholder="$t('clinic.lbl_first_name')" v-model="first_name" :error-message="errors['first_name']" :error-messages="errorMessages['first_name']"></InputField>
            </div>
            <div class="form-group">
              <label for="" class="form-label">{{ $t('customer.lbl_last_name') }}<span class="text-danger">*</span></label>
              <InputField class="" :is-required="true" :label="$t('employee.lbl_last_name')" :placeholder="$t('clinic.lbl_last_name')" v-model="last_name" :error-message="errors['last_name']" :error-messages="errorMessages['last_name']" autocomplete="off"> </InputField>
            </div>
            <div class="form-group">
              <label for="" class="form-label">{{ $t('customer.lbl_Email') }}<span class="text-danger">*</span></label>
              <InputField class="" :is-required="true" :label="$t('employee.lbl_Email')" :placeholder="$t('clinic.lbl_Email')" v-model="email" :error-message="errors['email']" :error-messages="errorMessages['email']"></InputField>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">{{ $t('clinic.lbl_gender') }} <span class="text-danger">*</span></label>
              <Multiselect id="gender" v-model="gender" :value="gender" v-bind="gender_data" class="form-group"></Multiselect>
              <span v-if="errorMessages['gender']">
                <ul class="text-danger">
                  <li v-for="err in errorMessages['gender']" :key="err">{{ err }}</li>
                </ul>
              </span>
              <span class="text-danger">{{ errors.gender }}</span>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">{{ $t('employee.lbl_phone_number') }}<span class="text-danger">*</span> </label>
              <vue-tel-input :value="mobile" @input="handleInput" v-bind="{ mode: 'international', maxLen: 15 }" autocomplete="new-password" :inputOptions="{ placeholder: $t('employee.lbl_phone_number_placeholder') }"></vue-tel-input>
              <span class="text-danger">{{ errors['mobile'] }}</span>
            </div>
          </div>
          <div class="col-md-6"  v-if="currentId === 0">
            <div class="form-group">
              <label for="" class="form-label">{{ $t('profile.lbl_password') }}<span class="text-danger">*</span></label>
              <InputField type="password" class="" :is-required="true" :label="$t('employee.lbl_password')" :placeholder="$t('employee.lbl_password')" v-model="password" :error-message="errors['password']" :autocomplete="newpassword" :error-messages="errorMessages['password']"></InputField>
            </div>
          </div>
          <div class="col-md-6"  v-if="currentId === 0">
            <div class="form-group">
              <label for="" class="form-label">{{ $t('profile.lbl_confirm_password') }} <span class="text-danger">*</span></label>
              <InputField type="password" class="" :is-required="true" :label="$t('employee.lbl_confirm_password')" :placeholder="$t('employee.lbl_confirm_password')" v-model="confirm_password" :error-message="errors['confirm_password']" :error-messages="errorMessages['confirm_password']"></InputField>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label" for="date_of_birth">{{ $t('customer.lbl_date_of_birth') }}<span class="text-danger">*</span></label>
              <flat-pickr
                id="date_of_birth"
                v-model="date_of_birth"
                :config="config"
                class="form-control"
                :placeholder="$t('employee.date_of_birth')"
              />
              <span v-if="errorMessages['date_of_birth']">
                <ul class="text-danger">
                  <li v-for="err in errorMessages['date_of_birth']" :key="err">{{ err }}</li>
                </ul>
              </span>
              <span class="text-danger">{{ errors.date_of_birth }}</span>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="" class="form-label">{{ $t('customer.lbl_status') }}</label>
              <div class="d-flex justify-content-between align-items-center form-control">
                <label class="form-label m-0" for="category-status">{{ $t('employee.lbl_status') }}</label>
                <div class="form-check form-switch">
                  <input class="form-check-input" :value="1" name="status" id="category-status" type="checkbox" v-model="status" />
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 mt-4">
            <h5 class="mb-3">{{ $t('customer.other_details') }}</h5>
          </div>
          <div class="col-md-12">
            <div class="form-group">
              <label class="form-label" for="address">{{ $t('clinic.lbl_address') }}</label>
              <textarea class="form-control" v-model="address" id="address" :placeholder="$t('clinic.lbl_address')"></textarea>
              <span v-if="errorMessages['address']">
                <ul class="text-danger">
                  <li v-for="err in errorMessages['address']" :key="err">{{ err }}</li>
                </ul>
              </span>
              <span class="text-danger">{{ errors.address }}</span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">{{ $t('clinic.lbl_country') }}</label>
              <Multiselect id="country-list" v-model="country" :placeholder="$t('clinic.lbl_country')" :value="country" v-bind="singleSelectOption" :options="countries.options" @select="getState" class="form-group"></Multiselect>
              <span v-if="errorMessages['country']">
                <ul class="text-danger">
                  <li v-for="err in errorMessages['country']" :key="err">{{ err }}</li>
                </ul>
              </span>
              <span class="text-danger">{{ errors['country'] }}</span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">{{ $t('clinic.lbl_state') }}</label>
              <Multiselect id="state-list" v-model="state" :placeholder="$t('clinic.lbl_state')" :value="state" v-bind="singleSelectOption" :options="states.options" @select="getCity" class="form-group"></Multiselect>
              <span v-if="errorMessages['state']">
                <ul class="text-danger">
                  <li v-for="err in errorMessages['state']" :key="err">{{ err }}</li>
                </ul>
              </span>
              <span class="text-danger">{{ errors['state'] }}</span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">{{ $t('clinic.lbl_city') }}</label>
              <Multiselect id="city-list" v-model="city" :placeholder="$t('clinic.lbl_city')" :value="city" v-bind="singleSelectOption" :options="cities.options" class="form-group"></Multiselect>
              <span v-if="errorMessages['city']">
                <ul class="text-danger">
                  <li v-for="err in errorMessages['city']" :key="err">{{ err }}</li>
                </ul>
              </span>
              <span class="text-danger">{{ errors['city'] }}</span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="" class="form-label">{{ $t('clinic.lbl_postal_code') }}</label>
              <InputField class="" type="text" :is-required="true" :label="$t('clinic.lbl_postal_code')" :placeholder="$t('clinic.lbl_postal_code')" v-model="pincode" :error-message="errors['pincode']" :error-messages="errorMessages['pincode']"></InputField>
            </div>
          </div>
        </div>

      </div>

      <FormFooter :IS_SUBMITED="IS_SUBMITED"></FormFooter>
    </div>
  </form>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { EDIT_URL, STORE_URL, UPDATE_URL ,COUNTRY_URL, STATE_URL, CITY_URL} from '../constant/customer'
import { useField, useForm } from 'vee-validate'

import { VueTelInput } from 'vue3-tel-input'

import { useModuleId, useRequest, useOnOffcanvasHide } from '@/helpers/hooks/useCrudOpration'
import * as yup from 'yup'

import { readFile } from '@/helpers/utilities'
import { useSelect } from '@/helpers/hooks/useSelect'

import FormHeader from '@/vue/components/form-elements/FormHeader.vue'
import FormFooter from '@/vue/components/form-elements/FormFooter.vue'
import InputField from '@/vue/components/form-elements/InputField.vue'
import FormElement from '@/helpers/custom-field/FormElement.vue'
import FlatPickr from 'vue-flatpickr-component'

// props
const props = defineProps({
  createTitle: { type: String, default: '' },
  editTitle: { type: String, default: '' },
  defaultImage: { type: String, default: '/img/default.webp' },
  customefield: { type: Array, default: () => [] },
  selectedSessionServiceProviderId: { type: Number, default: null }
})

const config = ref({
  dateFormat: 'Y-m-d',
  static: true,
  maxDate: 'today'
})

// Select Options
const singleSelectOption = ref({
  closeOnSelect: true,
  searchable: true
})
const gender_data = ref({
  searchable: true,
  options: [
    { label: 'Male', value: 'male' },
    { label: 'Female', value: 'female' },
    { label: 'Intersex', value: 'intersex' },
  ],
  closeOnSelect: true,
  createOption: false
})

const { getRequest, storeRequest, updateRequest } = useRequest()

// Edit Form Or Create Form
const currentId = useModuleId(() => {
  if (currentId.value > 0) {
    getRequest({ url: EDIT_URL, id: currentId.value }).then((res) => {
      if (res.status && res.data) {
        setFormData(res.data)

        getState(country.value)
        getCity(state.value)

      }
    })
  } else {
    setFormData(defaultData())
  }
})



onMounted(() => {
  setFormData(defaultData())
  getCountry()
})



const countries = ref({ options: [], list: [] })

  const getCountry = () => {

    useSelect({ url: COUNTRY_URL }, { value: 'id', label: 'name' }).then((data) => (countries.value = data))
  }

  const states = ref({ options: [], list: [] })

  const getState = (value) => {

    useSelect({ url: STATE_URL, data: value }, { value: 'id', label: 'name' }).then((data) => (states.value = data))
  }

  const cities = ref({ options: [], list: [] })

  const getCity = (value) => {
    useSelect({ url: CITY_URL, data: value }, { value: 'id', label: 'name' }).then((data) => (cities.value = data))
  }


/*
 * Form Data & Validation & Handeling
 */
// Default FORM DATA
const defaultData = () => {
  errorMessages.value = {}
  return {
    id: null,
    first_name: '',
    last_name: '',
    email: '',
    mobile: '',
    password: '',
    confirm_password: '',
    gender: 'male',
    profile_image: [],
    date_of_birth:'',
    address: '',
    city: '',
    country: '',
    pincode: '',
    state:'',
    status: 1,
    custom_fields_data: {}
  }
}

const image_url = ref()
const profileInputRef = ref(null)

// File upload methods
const triggerFileInput = () => {
  if (profileInputRef.value) {
    profileInputRef.value.click()
  }
}

const fileUpload = (event) => {
  const file = event.target.files?.[0]
  if (!file) return

  // Validate file type
  const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif']
  if (!validTypes.includes(file.type)) {
    // Show error message
    const errorDiv = document.getElementById('file-format-error')
    if (errorDiv) {
      errorDiv.textContent = 'Please select a valid image file (JPEG, JPG, PNG, GIF)'
      errorDiv.classList.remove('d-none')
    }
    event.target.value = ''
    return
  }

  // Validate file size (max 5MB)
  const maxSize = 5 * 1024 * 1024 // 5MB in bytes
  if (file.size > maxSize) {
    const errorDiv = document.getElementById('file-format-error')
    if (errorDiv) {
      errorDiv.textContent = 'File size must be less than 5MB'
      errorDiv.classList.remove('d-none')
    }
    event.target.value = ''
    return
  }

  // Clear any previous errors
  const errorDiv = document.getElementById('file-format-error')
  if (errorDiv) {
    errorDiv.classList.add('d-none')
  }

  // Create preview
  const reader = new FileReader()
  reader.onload = (e) => {
    profile_image.value = e.target.result
    image_url.value = e.target.result
  }
  reader.readAsDataURL(file)
}

const removeImage = () => {
  profile_image.value = null
  image_url.value = defaultImage.value
  if (profileInputRef.value) {
    profileInputRef.value.value = ''
  }
}

//  Reset Form
const setFormData = (data) => {
  image_url.value = data.profile_image || defaultImage.value
  resetForm({
    values: {
      id: data.id,
      first_name: data.first_name,
      last_name: data.last_name,
      email: data.email,
      mobile: data.mobile,
      password: data.password,
      confirm_password: data.confirm_password,
      date_of_birth:data.date_of_birth,
      date_of_birth: data.date_of_birth,
      address: data.address,
      city: data.city,
      state: data.state,
      country: data.country,
      pincode: data.pincode,
      profile_image: data.profile_image,
      status: data.status ? true : false,
      gender: data.gender,

      custom_fields_data: data.custom_field_data
    }
  })
}

// Reload Datatable, SnackBar Message, Alert, Offcanvas Close
const reset_datatable_close_offcanvas = (res) => {
  IS_SUBMITED.value = false
  if (res.status) {
    window.successSnackbar(res.message)
    renderedDataTable.ajax.reload(null, false)
    bootstrap.Offcanvas.getInstance('#form-offcanvas').hide()
    setFormData(defaultData())

  } else {
    window.errorSnackbar(res.message)
    errorMessages.value = res.all_message
  }
}
const numberRegex = /^\d+$/
const EMAIL_REGX = /^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/
// Validations

const validationSchema = yup.object({
  first_name: yup
    .string()
    .required('First name is a required field')
    .test('is-string', 'Only strings are allowed', (value) => {
      const specialCharsRegex = /[!@#$%^&*(),.?":{}|<>\-_;'\/+=\[\]\\]/
      return !specialCharsRegex.test(value) && !numberRegex.test(value)
    }),
  last_name: yup
    .string()
    .required('Last name is a required field')
    .test('is-string', 'Only strings are allowed', (value) => {
      const specialCharsRegex = /[!@#$%^&*(),.?":{}|<>\-_;'\/+=\[\]\\]/
      return !specialCharsRegex.test(value) && !numberRegex.test(value)
    }),
  email: yup
    .string()
    .required('Email is a required field')
    .test(
      'is-string',
      'Only alphabetic characters are allowed at the beginning',
      (value) => !numberRegex.test(value)
    )
    .matches(EMAIL_REGX, 'Must be a valid email'),
  mobile: yup
    .string()
    .required('Phone Number is a required field')
    .matches(/^(\+?\d+)?(\s?\d+)*$/, 'Phone Number must contain only digits'),

  password: yup
    .string()
    .test('password', 'Password is required', function (value) {
      if (currentId.value === 0 && !value) {
        return false
      }
      return true
    })
    .min(8, 'Password must be 8 to 14 characters')
    .max(14, 'Password must be 8 to 14 characters')
    .matches(
      /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/,
      'Password must contain at least one uppercase, lowercase, number, and special character.'
    ),

  confirm_password: yup
    .string()
    .test('confirm_password', 'confirm password is required', function (value) {
      // On create → required
      if (currentId.value === 0 && !value) {
        return false
      }
      // On edit → optional, but must match if filled
      if (value && value !== this.parent.password) {
        return this.createError({ message: 'Confirm password must match the password' })
      }
      return true
    }),

  date_of_birth: yup
    .date()
    .required('Date of Birth is required')
    .typeError('Invalid date format')
    .max(new Date(), 'Date of birth cannot be in the future'),
})


const { handleSubmit, errors, resetForm } = useForm({
  validationSchema,
  validateOnMount: false,
  validateOnBlur: false,
  validateOnChange: false,
  validateOnInput: false
})

const { value: id } = useField('first_name')
const { value: first_name } = useField('first_name')
const { value: last_name } = useField('last_name')
const { value: email } = useField('email')
const { value: gender } = useField('gender')
const { value: mobile } = useField('mobile')
const { value: profile_image } = useField('profile_image')
const { value: custom_fields_data } = useField('custom_fields_data')
const { value: password } = useField('password')
const { value: confirm_password } = useField('confirm_password')
const {value : date_of_birth}=useField('date_of_birth')
const { value: address } = useField('address')
const { value: city } = useField('city')
const { value: state } = useField('state')
const { value: country } = useField('country')
const { value: pincode } = useField('pincode')
const { value: status } = useField('status')

const errorMessages = ref({})

// phone number
const handleInput = (phone, phoneObject) => {
  // Handle the input event
 if (phoneObject?.countryCallingCode && phoneObject?.nationalNumber) {
    // Ensure country code starts with "+"
    const dialCode = phoneObject.countryCallingCode.startsWith('+')
      ? phoneObject.countryCallingCode
      : `+${phoneObject.countryCallingCode}`;
    mobile.value = `${dialCode} ${phoneObject.nationalNumber}`;
  } else if (phoneObject?.formatted) {
    mobile.value = phoneObject.formatted;
  }
}
const IS_SUBMITED = ref(false)
// Form Submit
const formSubmit = handleSubmit((values) => {
  IS_SUBMITED.value = true
  values.custom_fields_data = JSON.stringify(values.custom_fields_data)
  if (currentId.value > 0) {
    updateRequest({ url: UPDATE_URL, id: currentId.value, body: values, type: 'file' }).then((res) => reset_datatable_close_offcanvas(res))
  } else {
    storeRequest({ url: STORE_URL, body: values, type: 'file' }).then((res) => reset_datatable_close_offcanvas(res))
  }
})

useOnOffcanvasHide('form-offcanvas', () => setFormData(defaultData()))

const offcanvasSideClass = computed(() => {
  return document.documentElement.dir === 'rtl' ? 'offcanvas-start' : 'offcanvas-end';
});
</script>
